#!/usr/bin/env bash
# Start the app for the synthetic-user suite on a throwaway database.
#   bash tests/e2e/serve.sh                         # sqlite (local)
#   E2E_DB=mysql DB_HOST=... bash tests/e2e/serve.sh  # CI uses MariaDB
# `artisan serve` only forwards variables listed in the env file, so settings go
# into .env.e2e (APP_ENV=e2e makes Laravel load it).
set -euo pipefail
cd "$(dirname "$0")/../.."
PORT="${E2E_PORT:-8130}"
BASE="$( [ -f .env ] && echo .env || echo .env.example )"
{
  echo "APP_ENV=e2e"
  echo "APP_DEBUG=false"
  echo "APP_URL=http://127.0.0.1:${PORT}"
  echo "E2E_HOOKS=true"
  echo "SUPERADMIN_EMAIL=admin@e2e.test"
  # No control center in the suite: /admin shows the "panel moved" page.
  echo "HOP_CONTROL_URL="
  echo "TURNSTILE_SITE_KEY=${TURNSTILE_SITE_KEY:-}"
  echo "TURNSTILE_SECRET_KEY=${TURNSTILE_SECRET_KEY:-}"
  echo "CACHE_STORE=file"
  echo "SESSION_DRIVER=file"
  # Plain http on 127.0.0.1: a Secure cookie would be dropped by Playwright's request API.
  echo "SESSION_SECURE_COOKIE=false"
  echo "QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}"
  echo "MAIL_MAILER=log"
  echo "SPAMHAUS_DBL=false"
  # Each synthetic user sends its own X-Forwarded-For, like real visitors behind a proxy.
  echo "TRUSTED_PROXIES=127.0.0.1"
  echo "PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-8}"
  if [ "${E2E_DB:-sqlite}" = "sqlite" ]; then
    echo "DB_CONNECTION=sqlite"
    echo "DB_DATABASE=$PWD/database/e2e.sqlite"
  else
    echo "DB_CONNECTION=mysql"
    for v in DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do echo "$v=${!v:-}"; done
  fi
  grep -vE '^(APP_ENV|APP_DEBUG|APP_URL|DB_|CACHE_STORE|SESSION_DRIVER|SESSION_SECURE_COOKIE|QUEUE_CONNECTION|MAIL_MAILER|TURNSTILE_|SUPERADMIN_EMAIL|TRUSTED_PROXIES|SPAMHAUS_DBL|PHP_CLI_SERVER_WORKERS|E2E_|HOP_CONTROL_URL)' "$BASE"
} > .env.e2e
grep -q '^APP_KEY=base64' .env.e2e || echo "APP_KEY=base64:$(head -c 32 /dev/urandom | base64)" >> .env.e2e
export APP_ENV=e2e
[ "${E2E_DB:-sqlite}" = "sqlite" ] && rm -f database/e2e.sqlite && touch database/e2e.sqlite
php artisan config:clear -q
php artisan migrate:fresh --force -q
exec php artisan serve --host=127.0.0.1 --port="$PORT" --no-reload
