#!/usr/bin/env bash
# HEL.ink SSO check (READ-ONLY). Changes nothing, prints no secret values.
# Shows how PixelHop, NoteHop and RemoteHop are connected to HEL.ink accounts.
#
#   curl -fsSL https://raw.githubusercontent.com/navi-crwn/hel.ink/main/scripts/sso-check.sh -o /tmp/sso-check.sh
#   sudo bash /tmp/sso-check.sh /var/www/helink/shortlink-app /pixelhop /remotehop [/notehop]
#
# Secrets are compared by fingerprint only (first 12 hex characters of sha256).
# Matching fingerprints mean the apps share the same secret.

set -u
HELINK="${1:-/var/www/helink/shortlink-app}"
PIXEL="${2:-}"
REMOTE="${3:-}"
NOTE="${4:-}"
section() { printf '\n==== %s ====\n' "$1"; }
fp() { [ -n "$1" ] && printf '%s' "$1" | sha256sum | cut -c1-12 || echo "(empty)"; }
envval() { grep -hE "^$2=" "$1" 2>/dev/null | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

# Find where each app keeps its env (.env, docker-compose env_file, systemd EnvironmentFile).
envfiles() {
  local d="$1"
  find "$d" -maxdepth 3 \( -name '.env' -o -name '.env.*' -o -name '*.env' \) -not -path '*/node_modules/*' -not -path '*/vendor/*' 2>/dev/null
  grep -rhoE 'EnvironmentFile=-?[^ ]+' /etc/systemd/system 2>/dev/null | cut -d= -f2 | tr -d '-' | grep -i "$(basename "$d")" || true
}

section "HEL.ink (provider) at $HELINK"
cd "$HELINK" 2>/dev/null || { echo "Folder not found"; exit 1; }
echo "Commit: $(git log -1 --format='%h %ci %s' 2>/dev/null)"
echo "Uncommitted SSO files (empty = clean):"
git status --porcelain 2>/dev/null | grep -iE 'sso|pixelhop|remotehop|oauth' || echo "  (none)"
echo "PIXELHOP_URL           = $(envval .env PIXELHOP_URL)"
echo "PIXELHOP_SSO_SECRET fp = $(fp "$(envval .env PIXELHOP_SSO_SECRET)")  (length $(envval .env PIXELHOP_SSO_SECRET | tr -d '\n' | wc -c))"
echo "NOTEHOP_URL            = $(envval .env NOTEHOP_URL)"
echo "NOTEHOP_SSO_SECRET fp  = $(fp "$(envval .env NOTEHOP_SSO_SECRET)")  (length $(envval .env NOTEHOP_SSO_SECRET | tr -d '\n' | wc -c))"
echo "Other SSO-looking keys in .env (names only):"
grep -oE '^[A-Z0-9_]*(SSO|REMOTEHOP|OAUTH|CLIENT_SECRET)[A-Z0-9_]*' .env 2>/dev/null | sed 's/^/  /' || true
echo "SSO routes:"
sudo -u www-data env HOME=/tmp php8.3 artisan route:list 2>/dev/null | grep -iE 'sso|pixelhop|remotehop|oauth' | sed 's/^/  /'
echo "Code mentioning remotehop (app/ routes/ config/):"
grep -rniE 'remotehop|remote-hop|remote_hop' app routes config 2>/dev/null | head -20 | sed 's/^/  /' || true

check_client() {
  local name="$1" dir="$2"
  section "$name at ${dir:-<not given>}"
  [ -n "$dir" ] && [ -d "$dir" ] || { echo "Folder not given or not found"; return; }
  cd "$dir" || return
  [ -d .git ] && echo "Commit: $(git log -1 --format='%h %ci %s' 2>/dev/null)  remote: $(git remote get-url origin 2>/dev/null | sed -E 's#//[^@]+@#//***@#')"
  [ -d .git ] && { echo "Uncommitted files:"; git status --porcelain | head -20 | sed 's/^/  /'; }
  echo "Env files:"; envfiles "$dir" | sed 's/^/  /'
  for f in $(envfiles "$dir"); do
    for k in $(grep -oE '^[A-Z0-9_]*(HELINK|SSO|PIXELHOP|NOTEHOP|TURNSTILE|SECRET)[A-Z0-9_]*' "$f" 2>/dev/null | sort -u); do
      v="$(envval "$f" "$k")"
      case "$k" in
        *SECRET*|*KEY*|*TOKEN*|*PASS*|*PWD*|*DSN*) echo "  $f: $k fp=$(fp "$v") len=${#v}" ;;
        *) echo "  $f: $k=$v" ;;
      esac
    done
  done
  echo "Code that talks to HEL.ink (paths it calls):"
  grep -rnoE '(/sso/[a-z0-9_-]+|/api/pixelhop/[a-z]+|pixelhop-api\||X-PixelHop-[A-Za-z]+|/auth/helink[a-z/_-]*)' \
    --include=*.go --include=*.php --include=*.js --include=*.ts --include=*.py . 2>/dev/null \
    | grep -v -e node_modules -e vendor | sort -u | head -30 | sed 's/^/  /'
  echo "Running processes / containers:"
  ps -eo user,cmd | grep -i "$name" | grep -v grep | head -5 | sed 's/^/  /'
  docker ps --format '{{.Names}} {{.Image}} {{.Status}}' 2>/dev/null | grep -i "$name" | sed 's/^/  /'
}
check_client pixelhop "$PIXEL"
check_client remotehop "$REMOTE"
check_client notehop "$NOTE"

section "nginx vhosts (server_name and SSO-related locations)"
grep -rhnE 'server_name|location .*(sso|auth|helink)' /etc/nginx/sites-enabled 2>/dev/null | sed 's/^/  /' | head -40

section "Live checks (no login, nothing changes)"
H="$(envval "$HELINK/.env" APP_URL)"; H="${H:-https://hel.ink}"
printf '  %-48s %s\n' "GET  $H/sso/pixelhop (guest -> login)" "$(curl -s -o /dev/null -w '%{http_code} -> %{redirect_url}' "$H/sso/pixelhop?state=aaaaaaaaaaaaaaaa&return_to=x")"
printf '  %-48s %s\n' "POST $H/api/pixelhop/login (unsigned)" "$(curl -s -X POST -H 'Content-Type: application/json' -d '{}' "$H/api/pixelhop/login")"
P="$(envval "$HELINK/.env" PIXELHOP_URL)"
[ -n "$P" ] && printf '  %-48s %s\n' "GET  $P/auth/helink (-> HEL.ink)" "$(curl -s -o /dev/null -w '%{http_code} -> %{redirect_url}' "$P/auth/helink")"

section "What to report"
echo "1. Does any app share a secret fingerprint with another (PIXELHOP_SSO_SECRET, NOTEHOP_SSO_SECRET, RemoteHop)? Each app must have its own."
echo "2. Which HEL.ink paths does RemoteHop call (code list above)?"
echo "3. Are there uncommitted SSO files on HEL.ink production? (code that is not on GitHub)"
echo "4. Is TURNSTILE set on PixelHop and RemoteHop (fingerprints above not '(empty)')?"
