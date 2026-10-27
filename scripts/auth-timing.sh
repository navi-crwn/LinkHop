#!/usr/bin/env bash
# Why are HEL.ink sign-in and sign-up slow? (READ-ONLY: changes nothing, prints no secrets.)
#
#   curl -fsSL https://raw.githubusercontent.com/navi-crwn/hel.ink/main/scripts/auth-timing.sh -o /tmp/auth-timing.sh
#   sudo bash /tmp/auth-timing.sh /var/www/helink/shortlink-app
#
# Times every outside service a sign-in or sign-up waits for, and how busy the server is.

set -u
APP="${1:-/var/www/helink/shortlink-app}"
cd "$APP" 2>/dev/null || { echo "Folder not found: $APP"; exit 1; }
envval() { grep -hE "^$1=" .env 2>/dev/null | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
section() { printf '\n==== %s ====\n' "$1"; }
URL="$(envval APP_URL)"; URL="${URL:-https://hel.ink}"

section "Pages (3 tries each; ttfb = time until the server answered)"
for p in /login /register; do
  for i in 1 2 3; do
    curl -s -o /dev/null -w "  GET $p  code=%{http_code}  dns=%{time_namelookup}s  ttfb=%{time_starttransfer}s  total=%{time_total}s\n" "$URL$p"
  done
done

section "Outside services a request waits for"
# Cloudflare Turnstile: every sign-in/sign-up checks the token here (5 s limit).
curl -s -o /dev/null -w "  Turnstile siteverify  code=%{http_code}  connect=%{time_connect}s  total=%{time_total}s\n" \
  -d 'secret=timing-check&response=x' https://challenges.cloudflare.com/turnstile/v0/siteverify
# SMTP: sign-up and password-reset emails.
MH="$(envval MAIL_HOST)"; MP="$(envval MAIL_PORT)"
echo "  MAIL_MAILER=$(envval MAIL_MAILER)  MAIL_HOST=$MH  MAIL_PORT=$MP"
if [ -n "$MH" ] && [ -n "$MP" ]; then
  s=$(date +%s.%N)
  if timeout 15 bash -c "exec 3<>/dev/tcp/$MH/$MP" 2>/dev/null; then r=ok; else r=FAILED; fi
  echo "  SMTP connect $r in $(awk -v a="$s" -v b="$(date +%s.%N)" 'BEGIN{printf "%.3f", b-a}')s"
fi
echo "  SESSION_DRIVER=$(envval SESSION_DRIVER)  CACHE_STORE=$(envval CACHE_STORE)$(envval CACHE_DRIVER)  QUEUE_CONNECTION=$(envval QUEUE_CONNECTION)"

section "Server load (other apps on this VPS share it)"
uptime
free -m
echo "  CPU cores: $(nproc)"
echo "  Top memory/CPU users:"
ps -eo pid,user,%cpu,%mem,rss,etime,comm --sort=-%cpu | head -12 | sed 's/^/  /'
echo "  php-fpm workers:"
ps -eo comm,args | grep -c '[p]hp-fpm: pool' | sed 's/^/  total: /'
grep -hE '^\s*pm(\.max_children)?\s*=' /etc/php/8.3/fpm/pool.d/*.conf 2>/dev/null | sed 's/^/  /'
grep -h 'max_children' /var/log/php8.3-fpm.log 2>/dev/null | tail -3 | sed 's/^/  /'

section "Recent errors that mean waiting (last 2000 lines of the Laravel log)"
tail -n 2000 storage/logs/laravel.log 2>/dev/null \
  | grep -oE '(Turnstile verification failed|Failed to send welcome email|cURL error [0-9]+|timed out|Connection could not be established|Maximum execution time)' \
  | sort | uniq -c | sed 's/^/  /' || true

section "What to report"
echo "1. ttfb of /login and /register (above 1 s is slow)."
echo "2. Turnstile total and SMTP connect time (above 1 s each adds directly to sign-in / sign-up)."
echo "3. Load average vs CPU cores, free memory, and which processes use the most CPU/RAM."
echo "4. Any counts in the error section."
