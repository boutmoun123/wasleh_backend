#!/usr/bin/env sh
set -eu

export APP_URL="${APP_URL:-https://${RENDER_EXTERNAL_HOSTNAME:-localhost}}"

case "${APP_KEY:-}" in
    base64:*) ;;
    '') echo "APP_KEY is missing." >&2; exit 1 ;;
    *) export APP_KEY="base64:${APP_KEY}" ;;
esac

php artisan config:cache
php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
