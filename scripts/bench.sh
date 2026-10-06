#!/usr/bin/env bash
# php-fpm (nginx) vs Octane (RoadRunner) on a PROTECTED endpoint. Same image/code/PHP/opcache, same MySQL + Redis,
# same worker count (4). Load generator: ApacheBench (official httpd image) inside the compose network.
# Needs: docker compose, jq. Results are printed and saved to docs/benchmark-raw.txt.
set -euo pipefail

COMPOSE=${COMPOSE:-docker compose}
NET=laravel-oauth2-api_default
REQUESTS=${REQUESTS:-10000}; CONCURRENCY=${CONCURRENCY:-16}; RUNS=${RUNS:-5}; WARMUP=${WARMUP:-2000}
OUT=docs/benchmark-raw.txt

# Rate limiting would 429 the benchmark: raise it for this run only (restore with `make up`).
export API_RATE_LIMIT=100000000
$COMPOSE --profile bench up -d --build --force-recreate --wait api fpm nginx
sleep 3

artisan() { $COMPOSE exec -T api php artisan "$@" 2>&1 | sed 's/\x1b\[[0-9;]*m//g'; }
OUTC=$(artisan passport:client --client --name="bench-$RANDOM" --no-interaction)
CID=$(echo "$OUTC" | awk '/Client ID/{print $NF}'); SECRET=$(echo "$OUTC" | awk '/Client Secret/{print $NF}')
TOKEN=$(curl -s localhost:${API_PORT:-8088}/oauth/token -d grant_type=client_credentials -d client_id="$CID" -d client_secret="$SECRET" -d "scope=tasks:read tasks:write" | jq -r .access_token)

for i in $(seq 1 30); do # 30 tasks x 2 items for this client
  curl -s -o /dev/null localhost:${API_PORT:-8088}/api/tasks -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
    -d "{\"title\":\"bench task $i\",\"items\":[{\"title\":\"a\"},{\"title\":\"b\"}]}"
done

ab() { docker run --rm --network $NET httpd:2.4-alpine ab "$@" 2>/dev/null; }
URL_PATH='/api/tasks?per_page=20'
{
  echo "# $(date -u +%FT%TZ)  host: $(sysctl -n machdep.cpu.brand_string 2>/dev/null || uname -m), cpus=$(sysctl -n hw.ncpu 2>/dev/null)"
  echo "# docker: $(docker info --format 'cpus={{.NCPU}} mem={{.MemTotal}}')"
  echo "# endpoint: GET $URL_PATH (Bearer JWT, 30 tasks x 2 items, 20 returned)  n=$REQUESTS c=$CONCURRENCY runs=$RUNS warmup=$WARMUP"
  for target in "octane http://api:8000" "fpm http://nginx:80"; do
    set -- $target
    ab -n "$WARMUP" -c "$CONCURRENCY" -k -H "Authorization: Bearer $TOKEN" "$2$URL_PATH" >/dev/null
  done
  for run in $(seq 1 "$RUNS"); do # interleaved, so drift (thermal, other processes) hits both equally
    for target in "octane http://api:8000" "fpm http://nginx:80"; do
      set -- $target
      echo "## $1 run $run"
      ab -n "$REQUESTS" -c "$CONCURRENCY" -k -H "Authorization: Bearer $TOKEN" "$2$URL_PATH" \
        | grep -E "^(Complete requests|Failed requests|Non-2xx|Requests per second|Time per request.*mean\)|  (50|95|99|100)%)"
    done
  done
} | tee "$OUT"
echo; echo "Raw results saved to $OUT. Run 'make up' to restore the normal rate limit."
