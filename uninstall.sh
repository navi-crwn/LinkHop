#!/usr/bin/env bash
# LinkHop: uninstall. Run it in the folder where LinkHop is installed. --help lists the options.
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
# Uninstall LinkHop (Laravel). By default only the generated parts go (installed
# packages, built files, start script). Your data goes only if you say so.
# ---------------------------------------------------------------------------
PURGE=0; IS_HUB=0
while [ $# -gt 0 ]; do
  case "$1" in
    -y|--yes) YES=1 ;; --dry-run) DRY=1 ;; --purge) PURGE=1 ;;
    -h|--help) say "Usage: ./uninstall.sh [--purge] [--yes] [--dry-run]"; say "  --purge  also delete .env, the SQLite file, uploads and logs (asks you to type the site name)"; exit 0 ;;
    *) bad "Unknown option: $1"; exit 2 ;;
  esac; shift
done
SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" && pwd)"
cd "$SELF_DIR"
[ -f artisan ] || die "Run this from the LinkHop folder (artisan not found here)."
hdr "Uninstalling LinkHop from $SELF_DIR"

DBC="$(get_env .env DB_CONNECTION 2>/dev/null || true)"; DBF="$(get_env .env DB_DATABASE 2>/dev/null || true)"
say "  Will remove:     vendor/  node_modules/  public/build  public/storage (link)  start.sh  cached files"
if [ "$PURGE" = 1 ]; then
  say "  ${R}Will also delete your data:${Z}  .env  storage/ (uploads, logs)  backups/"
  [ "$DBC" = sqlite ] && say "                   the SQLite database $DBF"
  [ "$DBC" = mysql ] && say "                   (the MySQL database is not touched: drop it yourself if you want it gone)"
else
  say "  Will keep:       .env  database  uploads  logs  (run with --purge to remove them too)"
fi
if [ "$IS_HUB" = 1 ]; then warn "This is the hub. The other HOP apps sign in through it: they stop working without it."; fi
if [ "$PURGE" = 1 ] && [ "$DBC" = sqlite ] && [ "$IS_HUB" = 0 ]; then warn "The SQLite file is shared with the hub; this app will NOT delete it. Only its own tables stay unused."; fi
proceed "Continue?" || { say "Nothing was changed."; exit 0; }
if [ "$PURGE" = 1 ] && [ "$YES" = 0 ]; then
  NAME="$(get_env .env APP_NAME 2>/dev/null || echo 'LinkHop')"
  ask CONFIRM_NAME "Type the site name ($NAME) to confirm deleting your data" ""
  [ "$CONFIRM_NAME" = "$NAME" ] || die "Not confirmed: nothing was changed."
fi

rm_() { for p in "$@"; do if [ -e "$p" ] || [ -L "$p" ]; then if [ "$DRY" = 1 ]; then say "   (dry run) rm -rf $p"; else rm -rf "$p" && ok "Removed $p"; fi; fi; done; }
[ "$DRY" = 1 ] || php artisan queue:restart >/dev/null 2>&1 || true
rm_ vendor node_modules public/build public/hot public/storage start.sh bootstrap/cache/*.php
if [ "$PURGE" = 1 ]; then
  [ "$DBC" = sqlite ] && [ "$IS_HUB" = 1 ] && [ -n "$DBF" ] && rm_ "$DBF"
  rm_ .env storage/app storage/logs storage/framework/sessions storage/framework/cache backups
fi
hdr "Done"
if [ "$DRY" = 1 ]; then ok "Dry run finished: nothing was removed"; else ok "LinkHop was removed from this folder."; fi
say "  The folder itself ($SELF_DIR) is still here: delete it yourself when you are sure:  rm -rf $SELF_DIR"
say "  Services you created by hand (systemd, supervisor, nginx, cron lines) are not touched: remove those yourself."

}
main "$@"; exit $?
