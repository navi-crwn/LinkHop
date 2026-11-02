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

# PHP_BIN runs artisan and composer. Set PHP_BIN=/path/to/php to choose; by default php8.3 when it
# is installed (the version the apps run on), otherwise php.
if [ -z "${PHP_BIN:-}" ]; then if have php8.3; then PHP_BIN=php8.3; else PHP_BIN=php; fi; fi
composer_run() { "$PHP_BIN" "$(command -v composer)" "$@"; }

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

# set_env FILE KEY VALUE: replace "KEY=" or "#KEY=", or append. The new content is built in a
# temporary file next to FILE (same owner, group and mode) and moved over it, so FILE is never
# truncated half way: if anything fails, the old file is still there untouched.
_ENV_TMP=''
cleanup_env_tmp() { [ -n "$_ENV_TMP" ] && rm -f "$_ENV_TMP"; _ENV_TMP=''; return 0; }
set_env() {
  local f="$1" k="$2" v="$3"
  case "$v" in *[[:space:]\#\"\$\\]*|'') v="\"$(printf '%s' "$v" | sed 's/[\\"$]/\\&/g')\"" ;; esac
  _ENV_TMP="$(umask 077; mktemp "$f.XXXXXX")" || return 1
  # cp -p carries over mode and group (and the owner when allowed); its content is replaced next
  if [ -f "$f" ]; then cp -p "$f" "$_ENV_TMP" 2>/dev/null || chmod 600 "$_ENV_TMP"; fi
  if grep -qE "^#?[[:space:]]*${k}=" "$f" 2>/dev/null; then
    awk -v k="$k" -v v="$v" 'BEGIN{d=0} { if (!d && $0 ~ "^#?[ \t]*" k "=") { print k "=" v; d=1 } else print }' "$f" >"$_ENV_TMP" || { cleanup_env_tmp; return 1; }
  else
    { cat "$f" 2>/dev/null || true; printf '%s=%s\n' "$k" "$v"; } >"$_ENV_TMP" || { cleanup_env_tmp; return 1; }
  fi
  mv -f "$_ENV_TMP" "$f" || { cleanup_env_tmp; return 1; }
  _ENV_TMP=''
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
# If a step fails before the migrations ran, the previous version is put back by itself.
# From the migrations on the site stays in maintenance mode and the way back is printed.
# ---------------------------------------------------------------------------
FORCE=0; NOBACKUP=0
while [ $# -gt 0 ]; do
  case "$1" in
    -y|--yes) YES=1 ;; --dry-run) DRY=1 ;; --force) FORCE=1 ;; --no-backup) NOBACKUP=1 ;;
    -h|--help) say "Usage: ./update.sh [--yes] [--force] [--no-backup] [--dry-run]"; say "  --force      update even if you changed tracked files (your changes may conflict)"; say "  --no-backup  go on when the database cannot be copied first (with --yes the update stops instead)"; exit 0 ;;
    *) bad "Unknown option: $1"; exit 2 ;;
  esac; shift
done
SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" && pwd)"
cd "$SELF_DIR"
[ -f artisan ] || die "Run this from the LinkHop folder (artisan not found here)."
[ -f .env ] || die "No .env here: LinkHop is not installed in this folder. Run ./install.sh first."
have composer && have npm && have "$PHP_BIN" || die "php ($PHP_BIN), composer and npm are needed (run ./install.sh to see what is missing)."
have git && [ -d .git ] || die "This folder is not a git clone, so it cannot update itself. Download the new version and run ./install.sh again."

hdr "Updating LinkHop"
BEFORE="$(git describe --tags --always 2>/dev/null || git rev-parse --short HEAD)"
BEFORE_SHA="$(git rev-parse HEAD)"
if [ -n "$(git status --porcelain --untracked-files=no)" ] && [ "$FORCE" = 0 ]; then
  git status --short --untracked-files=no | head -10
  die "You changed files here (listed above). Commit or stash them, or run ./update.sh --force."
fi
confirm "Update now? (a copy of the database is saved first)" y || { say "Nothing was changed."; exit 0; }

# 1. Database copy, before anything changes. SQLite: a consistent copy (sqlite3 .backup, or VACUUM INTO
# through PHP, or a plain copy with a warning). MySQL/MariaDB: mysqldump --single-transaction with the
# password in a private temporary option file, never on the command line. Copies are private (0600).
DBC="$(get_env .env DB_CONNECTION)"
RESTORE_HINT=''; DUMP_CNF=''
no_backup() {
  # $1 = what is missing. Without a copy only a person at the keyboard may say "go on".
  warn "$1"
  if [ "$NOBACKUP" = 1 ]; then RESTORE_HINT='(no copy was made: restore your own backup)'; warn "Going on without a database copy (--no-backup)."; return 0; fi
  if [ "$YES" = 1 ] || [ "$INTERACTIVE" = 0 ]; then die "No database copy could be made, so nothing was changed. Make one yourself and run this again, or run ./update.sh --no-backup to continue without it."; fi
  confirm "Continue without a database copy?" n || { say "Nothing was changed."; exit 0; }
  RESTORE_HINT='(no copy was made: restore your own backup)'
}
backup_sqlite() {
  local dbf="$1" out; out="backups/database-$(date +%Y%m%d-%H%M%S).sqlite"
  mkdir -p backups; chmod 700 backups 2>/dev/null || true
  if have sqlite3 && (umask 077; sqlite3 "$dbf" ".backup '$out'") 2>/dev/null; then
    [ "$(sqlite3 "$out" 'PRAGMA integrity_check;' 2>/dev/null)" = ok ] || { rm -f "$out"; no_backup "The SQLite copy did not pass its integrity check."; return 0; }
  elif (umask 077; "$PHP_BIN" -r '$p = new PDO("sqlite:".$argv[1]); $p->exec("VACUUM INTO ".$p->quote($argv[2]));' "$dbf" "$out") 2>/dev/null; then
    :
  else
    warn "No consistent copy possible (sqlite3 is not installed and VACUUM INTO failed): copying the file as it is. Stop the queue worker first if the site is busy."
    (umask 077; cp -p "$dbf" "$out"; [ ! -f "$dbf-wal" ] || cp -p "$dbf-wal" "$out-wal") || { no_backup "Copying the SQLite file failed."; return 0; }
  fi
  chmod 600 "$out" 2>/dev/null || true
  RESTORE_HINT="cp $out '$dbf'   (stop the queue worker first)"
  ok "Database copied to $out"
}
backup_mysql() {
  local name host port user pass sock out dump extra done_
  name="$(get_env .env DB_DATABASE)"; host="$(get_env .env DB_HOST)"; port="$(get_env .env DB_PORT)"
  user="$(get_env .env DB_USERNAME)"; pass="$(get_env .env DB_PASSWORD)"; sock="$(get_env .env DB_SOCKET)"
  case "$name" in ''|*[!A-Za-z0-9_]*) die "DB_DATABASE in .env ('$name') has characters other than letters, digits and _. Nothing was changed. Make the copy by hand and fix the name, or ask for help." ;; esac
  dump=''; have mysqldump && dump=mysqldump
  [ -z "$dump" ] && have mariadb-dump && dump=mariadb-dump
  if [ -z "$dump" ]; then no_backup "mysqldump is not installed. Make a copy yourself:  mysqldump --single-transaction '$name' > backup.sql"; return 0; fi
  out="backups/database-$(date +%Y%m%d-%H%M%S).sql"
  mkdir -p backups; chmod 700 backups 2>/dev/null || true
  DUMP_CNF="$(umask 077; mktemp "${TMPDIR:-/tmp}/linkhop-dump.XXXXXX")" || die "Cannot create a temporary file for the dump."
  case "$user$pass$host$port$sock" in *$'\n'*) die "DB_* values in .env contain a line break; cannot make the dump." ;; esac
  {
    printf '[client]\n'
    [ -n "$user" ] && printf 'user="%s"\n' "$(printf '%s' "$user" | sed 's/[\\"]/\\&/g')"
    printf 'password="%s"\n' "$(printf '%s' "$pass" | sed 's/[\\"]/\\&/g')"
    if [ -n "$sock" ]; then printf 'socket="%s"\n' "$(printf '%s' "$sock" | sed 's/[\\"]/\\&/g')"
    else
      [ -n "$host" ] && printf 'host="%s"\n' "$(printf '%s' "$host" | sed 's/[\\"]/\\&/g')"
      [ -n "$port" ] && printf 'port="%s"\n' "$(printf '%s' "$port" | sed 's/[\\"]/\\&/g')"
    fi
  } >"$DUMP_CNF"
  # (MySQL 8 wants the PROCESS privilege for tablespaces unless told otherwise; MariaDB does not know that option)
  done_=0
  for extra in '' --no-tablespaces; do
    if (umask 077; "$dump" --defaults-extra-file="$DUMP_CNF" --single-transaction --quick --routines ${extra:+"$extra"} "$name" >"$out") 2>/dev/null && tail -n 1 "$out" | grep -q 'Dump completed'; then done_=1; break; fi
  done
  if [ "$done_" = 1 ]; then
    chmod 600 "$out" 2>/dev/null || true
    RESTORE_HINT="mysql '$name' < $out   (with the same user as in .env)"
    ok "Database dumped to $out"
  else
    rm -f "$out"
    no_backup "The database dump failed (user, password, host or permissions in .env?)."
  fi
  rm -f "$DUMP_CNF"; DUMP_CNF=''
}
if [ "$DRY" = 1 ]; then
  say "   (dry run) database copy skipped"
elif [ "$NOBACKUP" = 1 ]; then
  no_backup "Database copy skipped (--no-backup)."
elif [ "$DBC" = sqlite ]; then
  DBF="$(get_env .env DB_DATABASE)"; [ -n "$DBF" ] || DBF="$SELF_DIR/database/database.sqlite"
  if [ -f "$DBF" ]; then backup_sqlite "$DBF"; fi
elif [ "$DBC" = mysql ] || [ "$DBC" = mariadb ]; then
  backup_mysql
else
  no_backup "DB_CONNECTION '$DBC' has no automatic copy here."
fi

# 2. Code, packages, front end, migrations.
# STATE tells the exit handler what is safe: none (nothing touched) | before (code may be half
# replaced, the database is untouched) | migrating (the database may have changed) | finished.
STATE=none; STEP_NAME=''; TOUCHED=0
LOCAL_PATCH=''
restore_previous() {
  say ""; warn "The update failed at: ${STEP_NAME:-an early step}. The database was not touched; putting the previous version back."
  # nothing was replaced yet (the download itself failed): there is nothing to put back
  [ "$TOUCHED" = 1 ] || return 0
  if [ "$(git rev-parse HEAD 2>/dev/null)" != "$BEFORE_SHA" ]; then git reset --hard "$BEFORE_SHA" >/dev/null || return 1; fi
  composer_run install --no-dev --optimize-autoloader --no-interaction --no-progress || return 1
  npm ci --no-audit --no-fund --loglevel=error || return 1
  npm run build --silent || return 1
  "$PHP_BIN" artisan optimize:clear >/dev/null 2>&1 || true
  return 0
}
manual_steps() {
  say ""; bad "The site stays in maintenance mode. Nothing more was changed after the failure."
  say "  To go back to the version before this update ($BEFORE):"
  say "    1. Restore the database:  ${RESTORE_HINT:-(restore your own backup)}"
  say "    2. git reset --hard $BEFORE_SHA"
  say "    3. $PHP_BIN \$(command -v composer) install --no-dev --optimize-autoloader"
  say "    4. npm ci && npm run build"
  say "    5. $PHP_BIN artisan optimize:clear && $PHP_BIN artisan up"
  if [ "$STATE" = migrating ]; then say "  Or, if you can fix the cause: fix it, run  $PHP_BIN artisan migrate --force  and then  $PHP_BIN artisan up."; fi
  [ -n "$LOCAL_PATCH" ] && say "  Your own changes to tracked files were saved in $LOCAL_PATCH."
  return 0
}
finish() {
  local rc=$?
  trap - EXIT
  cleanup_env_tmp
  [ -n "$DUMP_CNF" ] && rm -f "$DUMP_CNF"
  if [ "$DRY" = 0 ]; then
    case "$STATE" in
      finished) "$PHP_BIN" artisan up >/dev/null 2>&1 || true ;;
      before)
        if restore_previous; then "$PHP_BIN" artisan up >/dev/null 2>&1 || true; ok "The previous version ($BEFORE) is back and the site is up. Fix the cause, then run ./update.sh again.${LOCAL_PATCH:+ Your own changes are in $LOCAL_PATCH.}"
        else manual_steps; fi ;;
      migrating) manual_steps ;;
    esac
  fi
  exit "$rc"
}
trap finish EXIT
trap 'exit 130' INT; trap 'exit 143' TERM

git symbolic-ref -q HEAD >/dev/null || die "This checkout is not on a branch. Run:  git checkout main  and try again."
if [ "$DRY" = 0 ]; then
  if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    mkdir -p backups; LOCAL_PATCH="backups/local-changes-$(date +%Y%m%d-%H%M%S).patch"
    (umask 077; git diff HEAD >"$LOCAL_PATCH"); ok "Your changes to tracked files were saved in $LOCAL_PATCH"
  fi
  "$PHP_BIN" artisan down --retry=30 >/dev/null 2>&1 || true
  STATE=before
fi
# step() with the name kept for the messages above
upd() { STEP_NAME="$1"; step "$@"; }
upd "Fetching the new version" git pull --ff-only
TOUCHED=1
upd "PHP packages (composer)" composer_run install --no-dev --optimize-autoloader --no-interaction --no-progress
upd "Front end (npm)" npm ci --no-audit --no-fund --loglevel=error
upd "Building the front end" npm run build --silent
[ "$DRY" = 1 ] || STATE=migrating
upd "Updating the database tables" "$PHP_BIN" artisan migrate --force
upd "Linking the storage folder" "$PHP_BIN" artisan storage:link --force
upd "Clearing and warming caches" "$PHP_BIN" artisan optimize:clear
upd "Warming caches" "$PHP_BIN" artisan optimize
upd "Telling queue workers to restart" "$PHP_BIN" artisan queue:restart
[ "$DRY" = 1 ] || STATE=finished
AFTER="$(git describe --tags --always 2>/dev/null || git rev-parse --short HEAD)"
hdr "Done"
ok "LinkHop updated: $BEFORE → $AFTER"
say "  If it runs as a service (systemd, supervisor), restart it. With ./start.sh, stop it (Ctrl+C) and start it again."

}
main "$@"; exit $?
