#!/usr/bin/env bash
# End-to-end OAuth2 walkthrough against the running stack (`make demo`). Needs: curl, jq, openssl, docker compose.
# Everything here uses DEV-ONLY credentials (demo@example.com / dev-only-password from the seeder).
set -euo pipefail

API=${API:-http://localhost:8088}
COMPOSE=${COMPOSE:-docker compose}
REDIRECT=https://app.example.test/callback
JAR=$(mktemp)
trap 'rm -f "$JAR"' EXIT

step() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
artisan() { $COMPOSE exec -T api php artisan "$@" 2>&1 | sed 's/\x1b\[[0-9;]*m//g'; }
field() { awk -v k="$1" '$0 ~ k {print $NF}'; }

step "1. Create a machine client (client credentials) with passport:client"
OUT=$(artisan passport:client --client --name="demo-machine-$RANDOM" --no-interaction)
CID=$(echo "$OUT" | field "Client ID"); SECRET=$(echo "$OUT" | field "Client Secret")
echo "client_id=$CID"

token() { # token <scopes>
  curl -s "$API/oauth/token" -d grant_type=client_credentials -d client_id="$CID" -d client_secret="$SECRET" -d "scope=$1" | jq -r .access_token
}

step "2. Get a token (client credentials, scopes tasks:read tasks:write)"
TOKEN=$(token "tasks:read tasks:write")
echo "access_token=${TOKEN:0:40}…"
curl -s "$API/api/me" -H "Authorization: Bearer $TOKEN" | jq -c .

step "3. Protected resource: create + list (expect 201, 200)"
echo -n "POST /api/tasks -> "; status -X POST "$API/api/tasks" -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"title":"Ship the demo","items":[{"title":"curl it"}]}'; echo
echo -n "GET  /api/tasks -> "; status "$API/api/tasks" -H "Authorization: Bearer $TOKEN"; echo
ETAG=$(curl -s -D - -o /dev/null "$API/api/tasks" -H "Authorization: Bearer $TOKEN" | awk -F': ' 'tolower($1)=="etag"{print $2}' | tr -d '\r')
echo -n "GET  /api/tasks with If-None-Match $ETAG -> "; status "$API/api/tasks" -H "Authorization: Bearer $TOKEN" -H "If-None-Match: $ETAG"; echo " (304 = revalidated)"

step "4. Insufficient scope: token with tasks:read only tries to write (expect 403)"
READONLY=$(token "tasks:read")
curl -s -X POST "$API/api/tasks" -H "Authorization: Bearer $READONLY" -H 'Content-Type: application/json' -d '{"title":"x"}' -w '\nHTTP %{http_code}\n'

step "5. No token (expect 401 + WWW-Authenticate)"
curl -s -i "$API/api/tasks" | sed -n '1p;/^[Ww][Ww][Ww]-/p;/^[Cc]ontent-[Tt]ype/p'; curl -s "$API/api/tasks"; echo

step "6. Revoke the token, then reuse it (expect 204 then 401)"
echo -n "POST /api/oauth/revoke -> "; status -X POST "$API/api/oauth/revoke" -H "Authorization: Bearer $TOKEN"; echo
echo -n "GET  /api/me           -> "; status "$API/api/me" -H "Authorization: Bearer $TOKEN"; echo

step "7. Authorization code + PKCE (public client, resource owner = demo@example.com)"
OUT=$(artisan passport:client --name="demo-spa-$RANDOM" --public --redirect_uri="$REDIRECT" --no-interaction)
PID=$(echo "$OUT" | field "Client ID")
VERIFIER=$(openssl rand -base64 60 | tr -d '=+/\n' | cut -c1-64)
CHALLENGE=$(printf %s "$VERIFIER" | openssl dgst -sha256 -binary | openssl base64 -A | tr '+/' '-_' | tr -d '=')
echo -n "POST /login -> "; status -c "$JAR" -b "$JAR" -X POST "$API/login" -H 'Accept: application/json' -d email=demo@example.com -d password=dev-only-password; echo
CONSENT=$(curl -s -c "$JAR" -b "$JAR" -H 'Accept: application/json' -G "$API/oauth/authorize" \
  --data-urlencode client_id="$PID" --data-urlencode redirect_uri="$REDIRECT" --data-urlencode response_type=code \
  --data-urlencode scope="tasks:read" --data-urlencode state=xyz \
  --data-urlencode code_challenge="$CHALLENGE" --data-urlencode code_challenge_method=S256)
echo "consent screen: $(echo "$CONSENT" | jq -c '{client, scopes}')"
AUTH_TOKEN=$(echo "$CONSENT" | jq -r .auth_token)
LOCATION=$(curl -s -o /dev/null -D - -c "$JAR" -b "$JAR" -X POST "$API/oauth/authorize" \
  -d state=xyz -d client_id="$PID" -d auth_token="$AUTH_TOKEN" | awk -F': ' 'tolower($1)=="location"{print $2}' | tr -d '\r')
echo "redirected to: ${LOCATION:0:90}…(code=…&state=xyz)"
CODE=$(echo "$LOCATION" | sed -E 's/.*[?&]code=([^&]+).*/\1/')
TOKENS=$(curl -s "$API/oauth/token" -d grant_type=authorization_code -d client_id="$PID" -d redirect_uri="$REDIRECT" \
  -d code_verifier="$VERIFIER" -d code="$CODE")
ACCESS=$(echo "$TOKENS" | jq -r .access_token); REFRESH=$(echo "$TOKENS" | jq -r .refresh_token)
echo "token response: $(echo "$TOKENS" | jq -c '{token_type, expires_in, has_refresh: (.refresh_token != null)}')"
curl -s "$API/api/me" -H "Authorization: Bearer $ACCESS" | jq -c .
echo -n "GET /api/tasks as the user (scope tasks:read; sees only the user's own tasks) -> "; status "$API/api/tasks" -H "Authorization: Bearer $ACCESS"; echo

step "8. Refresh token (rotation): new pair, old refresh token dies"
NEW=$(curl -s "$API/oauth/token" -d grant_type=refresh_token -d refresh_token="$REFRESH" -d client_id="$PID")
echo "refreshed: $(echo "$NEW" | jq -c '{token_type, expires_in}')"
echo -n "reusing the old refresh token -> "; curl -s "$API/oauth/token" -d grant_type=refresh_token -d refresh_token="$REFRESH" -d client_id="$PID" | jq -c .error

step "9. Password grant is not offered (expect unsupported_grant_type)"
curl -s "$API/oauth/token" -d grant_type=password -d client_id="$CID" -d client_secret="$SECRET" -d username=a@b.c -d password=x | jq -c .error
echo
