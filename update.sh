#!/usr/bin/env bash
# LinkHop: update. Run it in the folder where LinkHop is installed. --help lists the options.
APP='LinkHop'; SLUG='linkhop'; REPO='LinkHop'; PORT_DEFAULT=8001
# ---------------------------------------------------------------------------
# Shared helpers (the same in every HOP installer). Plain bash 3.2+, no extras.
# ---------------------------------------------------------------------------
set -eu
set -o pipefail

if [ -t 1 ]; then
  B=$'\033[1m'; D=$'\033[2m'; R=$'\033[31m'; G=$'\033[32m'; Y=$'\033[33m'; C=$'\033[36m'; Z=$'\033[0m'
else
  B=''; D=''; R=''; G=''; Y=''; C=''; Z=''
fi
say()  { printf '%s\n' "$*"; }
hdr()  { printf '\n%s== %s ==%s\n' "$B" "$*" "$Z"; }
ok()   { printf ' %s✓%s %s\n' "$G" "$Z" "$*"; }
warn() { printf ' %s!%s %s\n' "$Y" "$Z" "$*" >&2; }
bad()  { printf ' %s✗%s %s\n' "$R" "$Z" "$*" >&2; }
die()  { bad "$*"; exit 1; }
have() { command -v "$1" >/dev/null 2>&1; }

YES=0; DRY=0; STAR=ask; INTERACTIVE=0
[ -t 0 ] && INTERACTIVE=1
# Run through "curl ... | bash": stdin is the script, so ask on the terminal instead.
claim_tty() {
  [ "$INTERACTIVE" = 1 ] && return 0
  case " $* " in *" --yes "*|*" -y "*) return 0 ;; esac
  if (: </dev/tty) 2>/dev/null; then exec </dev/tty; INTERACTIVE=1; fi
}

# ask VAR "Question" "default": skips the question when VAR is already set
# (by a flag or the environment), and takes the default with --yes.
ask() {
  local var="$1" q="$2" def="${3-}" cur reply
  eval "cur=\${$var-}"
  [ -n "$cur" ] && return 0
  if [ "$YES" = 1 ] || [ "$INTERACTIVE" = 0 ]; then eval "$var=\$def"; return 0; fi
  if [ -n "$def" ]; then printf ' %s?%s %s %s[%s]%s ' "$C" "$Z" "$q" "$D" "$def" "$Z"; else printf ' %s?%s %s ' "$C" "$Z" "$q"; fi
  IFS= read -r reply || reply=''
  eval "$var=\${reply:-\$def}"
}
# ask_secret VAR "Question": no echo; empty means "generate" or "skip" for the caller.
ask_secret() {
  local var="$1" q="$2" cur reply
  eval "cur=\${$var-}"
  [ -n "$cur" ] && return 0
  if [ "$YES" = 1 ] || [ "$INTERACTIVE" = 0 ]; then eval "$var=''"; return 0; fi
  printf ' %s?%s %s ' "$C" "$Z" "$q"
  IFS= read -rs reply || reply=''; printf '\n'
  eval "$var=\$reply"
}
# choose VAR "Question" default opt...: the answer is one of the options.
choose() {
  local var="$1" q="$2" def="$3" cur a ok_=0; shift 3
  eval "cur=\${$var-}"
  if [ -n "$cur" ]; then for a in "$@"; do [ "$a" = "$cur" ] && ok_=1; done; [ $ok_ = 1 ] && return 0; die "--$var: '$cur' is not one of: $*"; fi
  if [ "$YES" = 1 ] || [ "$INTERACTIVE" = 0 ]; then eval "$var=\$def"; return 0; fi
  while :; do
    ask _pick "$q ($*)" "$def"; a="$_pick"; _pick=''
    for ok_ in "$@"; do [ "$ok_" = "$a" ] && { eval "$var=\$a"; return 0; }; done
    warn "Please type one of: $*"
  done
}
# confirm "Question" y|n: true when the answer is yes.
confirm() {
  local q="$1" def="${2:-y}" reply hint='[Y/n]'
  [ "$def" = n ] && hint='[y/N]'
  if [ "$YES" = 1 ] || [ "$INTERACTIVE" = 0 ]; then [ "$def" = y ]; return; fi
  printf ' %s?%s %s %s%s%s ' "$C" "$Z" "$q" "$D" "$hint" "$Z"
  IFS= read -r reply || reply=''
  case "${reply:-$def}" in y|Y|yes|YES|Yes) return 0 ;; *) return 1 ;; esac
}
# proceed "Question": like confirm (default no), but --yes means yes. For steps the person asked for by name.
proceed() { [ "$YES" = 1 ] && return 0; confirm "$1" n; }
rand_hex() { if have openssl; then openssl rand -hex "$1"; else LC_ALL=C tr -dc 'a-f0-9' </dev/urandom | head -c $(( $1 * 2 )); fi; }

# set_env FILE KEY VALUE: replace "KEY=" or "#KEY=" in place, or append.
set_env() {
  local f="$1" k="$2" v="$3" tmp
  case "$v" in *[[:space:]\#\"\$\\]*|'') v="\"$(printf '%s' "$v" | sed 's/[\\"$]/\\&/g')\"" ;; esac
  tmp="$(mktemp)"
  if grep -qE "^#?[[:space:]]*${k}=" "$f" 2>/dev/null; then
    awk -v k="$k" -v v="$v" 'BEGIN{d=0} { if (!d && $0 ~ "^#?[ \t]*" k "=") { print k "=" v; d=1 } else print }' "$f" >"$tmp"
  else
    cat "$f" >"$tmp" 2>/dev/null || true; printf '%s=%s\n' "$k" "$v" >>"$tmp"
  fi
  cat "$tmp" >"$f"; rm -f "$tmp"
}
get_env() { sed -n "s/^$2=//p" "$1" 2>/dev/null | head -1 | sed 's/^"//; s/"$//'; }

port_free() {
  if have lsof; then ! lsof -nP -iTCP:"$1" -sTCP:LISTEN >/dev/null 2>&1
  elif have ss; then ! ss -ltn "sport = :$1" 2>/dev/null | grep -q LISTEN
  else ! (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; fi
}
free_port() { local p="$1"; while ! port_free "$p"; do p=$((p + 1)); done; echo "$p"; }
disk_free_mb() { df -Pm "${1:-.}" 2>/dev/null | awk 'NR==2{print $4}'; }
version_ge() { [ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -1)" = "$2" ]; }  # version_ge HAVE NEED
open_url() { if have open; then open "$1" >/dev/null 2>&1 || true; elif have xdg-open; then xdg-open "$1" >/dev/null 2>&1 || true; else say "   $1"; fi; }

# After a good install: one question, never silent, never repeated, nothing sent anywhere.
star_prompt() {
  local url="https://github.com/navi-crwn/$REPO"
  [ "$STAR" = no ] && { say "   ${D}If you like $APP: $url${Z}"; return 0; }
  if [ "$STAR" = yes ] || confirm "Open the GitHub page of $APP so you can star it?" y; then
    if have gh && gh auth status >/dev/null 2>&1 && confirm "Star it right now with your GitHub login (gh)?" n; then
      gh api -X PUT "user/starred/navi-crwn/$REPO" >/dev/null 2>&1 && ok "Starred. Thank you!" || { warn "Could not star it; opening the page instead."; open_url "$url"; }
    else
      open_url "$url"
    fi
  fi
  say "   ${D}GitHub: $url${Z}"
}

# Run a step, show it, stop with a clear message when it fails.
step() { local msg="$1"; shift; printf ' %s…%s %s\n' "$D" "$Z" "$msg"; if [ "$DRY" = 1 ]; then say "   (dry run) $*"; return 0; fi; "$@" || die "Step failed: $msg"; }

common_usage() {
cat <<USAGE
Options:
  -y, --yes             No questions: use the defaults and any flags below
      --dir DIR         Install into DIR (default: this folder, or ~/$SLUG when run from a download)
      --name NAME       Name of your site, shown in the pages
      --url URL         Public address (default http://localhost:PORT)
      --port N          Port for the built-in server (default $PORT_DEFAULT)
      --run MODE        $RUN_MODES
      --hub MODE        existing | new | standalone$HUB_NOTE
      --hub-url URL     Address of the HOP ID hub (with --hub existing)
      --hub-secret S    Shared app secret (or set HOP_SSO_SECRET in the environment)
      --star / --no-star
      --dry-run         Show the steps without running them
  -h, --help
USAGE
}
main() {
# ---------------------------------------------------------------------------
# Update LinkHop (Laravel): backup, new code, packages, front end, migrations.
# ---------------------------------------------------------------------------
FORCE=0
while [ $# -gt 0 ]; do
  case "$1" in
    -y|--yes) YES=1 ;; --dry-run) DRY=1 ;; --force) FORCE=1 ;;
    -h|--help) say "Usage: ./update.sh [--yes] [--force] [--dry-run]"; say "  --force  update even if you changed tracked files (your changes may conflict)"; exit 0 ;;
    *) bad "Unknown option: $1"; exit 2 ;;
  esac; shift
done
SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" && pwd)"
cd "$SELF_DIR"
[ -f artisan ] || die "Run this from the LinkHop folder (artisan not found here)."
[ -f .env ] || die "No .env here: LinkHop is not installed in this folder. Run ./install.sh first."
have composer && have npm && have php || die "php, composer and npm are needed (run ./install.sh to see what is missing)."
have git && [ -d .git ] || die "This folder is not a git clone, so it cannot update itself. Download the new version and run ./install.sh again."

hdr "Updating LinkHop"
BEFORE="$(git describe --tags --always 2>/dev/null || git rev-parse --short HEAD)"
if [ -n "$(git status --porcelain --untracked-files=no)" ] && [ "$FORCE" = 0 ]; then
  git status --short --untracked-files=no | head -10
  die "You changed files here (listed above). Commit or stash them, or run ./update.sh --force."
fi
confirm "Update now? (a copy of the database is saved first)" y || { say "Nothing was changed."; exit 0; }

# 1. Database copy (SQLite). For MySQL/MariaDB this prints the command to run.
DBC="$(get_env .env DB_CONNECTION)"
if [ "$DBC" = sqlite ]; then
  DBF="$(get_env .env DB_DATABASE)"; [ -n "$DBF" ] || DBF="$SELF_DIR/database/database.sqlite"
  if [ -f "$DBF" ] && [ "$DRY" = 0 ]; then
    mkdir -p backups; cp "$DBF" "backups/database-$(date +%Y%m%d-%H%M%S).sqlite" && ok "Database copied to backups/"
  fi
else
  warn "MySQL/MariaDB: make a dump first if you have not, for example: mysqldump $(get_env .env DB_DATABASE) > backup.sql"
  confirm "Continue without a dump?" n || { say "Nothing was changed."; exit 0; }
fi

# 2. Code, packages, front end, migrations
[ "$DRY" = 1 ] || php artisan down --retry=30 >/dev/null 2>&1 || true
trap '[ "$DRY" = 1 ] || php artisan up >/dev/null 2>&1 || true' EXIT
git symbolic-ref -q HEAD >/dev/null || die "This checkout is not on a branch. Run:  git checkout main  and try again."
step "Fetching the new version" git pull --ff-only
step "PHP packages (composer)" composer install --no-dev --optimize-autoloader --no-interaction --no-progress
step "Front end (npm)" npm ci --no-audit --no-fund --loglevel=error
step "Building the front end" npm run build --silent
step "Updating the database tables" php artisan migrate --force
step "Linking the storage folder" php artisan storage:link --force
step "Clearing and warming caches" php artisan optimize:clear
step "Warming caches" php artisan optimize
step "Telling queue workers to restart" php artisan queue:restart
AFTER="$(git describe --tags --always 2>/dev/null || git rev-parse --short HEAD)"
hdr "Done"
ok "LinkHop updated: $BEFORE → $AFTER"
say "  If it runs as a service (systemd, supervisor), restart it. With ./start.sh, stop it (Ctrl+C) and start it again."

}
main "$@"; exit $?
