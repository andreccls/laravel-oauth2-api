#!/bin/sh
# Prepares the app at container start, then execs the main command.
set -e
cd /app

# Passport signing keys are generated once per storage volume (never baked into the image / git).
[ -f storage/oauth-private.key ] || php artisan passport:keys --no-interaction

# Production caches (config/routes/events). Env is read here, so this must run at start, not at build.
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan event:cache --no-interaction

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force --no-interaction
fi

exec "$@"
