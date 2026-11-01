#!/usr/bin/env bash
# LinkHop: one-command installer. Run it from a clone, or straight from GitHub:
#   curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/install.sh | bash
# Add --yes to take every default. --help lists the options.
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
# Install LinkHop (Laravel). Safe to run again: it never overwrites an existing
# .env without asking, and never deletes anything.
# ---------------------------------------------------------------------------
claim_tty "$@"
SELF="${BASH_SOURCE[0]:-$0}"
SELF_DIR="$(cd "$(dirname "$SELF")" 2>/dev/null && pwd || pwd)"
DIR=''; NAME=''; URL=''; PORT=''; RUN=''; HUB=''; HUB_URL=''; HUB_SECRET="${HOP_SSO_SECRET:-}"
DB=''; DB_PATH=''; DB_HOST=''; DB_PORT=''; DB_NAME=''; DB_USER=''; DB_PASS="${HOP_DB_PASSWORD:-}"
ADMIN=''; HUB_DIR=''; PUBLIC_URL=''; MAIL_HOST=''; MAIL_PORT=''; MAIL_USER=''; MAIL_FROM=''; MAIL_PASS="${HOP_MAIL_PASSWORD:-}"
RUN_MODES='serve (built-in server, default) | none (I will set up nginx or Apache myself)'
HUB_NOTE=''

usage() { say "Usage: ./install.sh [options]"; say ""; common_usage; cat <<U
      --db sqlite|mysql   Database (default sqlite)
      --db-path FILE      SQLite file (default database/database.sqlite)
      --db-host H --db-port N --db-name N --db-user U   MySQL/MariaDB (password: HOP_DB_PASSWORD)
      --admin EMAIL       Owner e-mail
      --hub-dir DIR       Where to install the hub (with --hub new)
      --mail-host H --mail-port N --mail-user U --mail-from ADDR   Outgoing e-mail (password: HOP_MAIL_PASSWORD)
U
}
while [ $# -gt 0 ]; do
  case "$1" in
    -y|--yes) YES=1 ;; --dry-run) DRY=1 ;; --star) STAR=yes ;; --no-star) STAR=no ;;
    --dir) DIR="$2"; shift ;; --name) NAME="$2"; shift ;; --url) URL="$2"; shift ;; --port) PORT="$2"; shift ;;
    --run) RUN="$2"; shift ;; --hub) HUB="$2"; shift ;; --hub-url) HUB_URL="$2"; shift ;; --hub-secret) HUB_SECRET="$2"; shift ;;
    --db) DB="$2"; shift ;; --db-path) DB_PATH="$2"; shift ;; --db-host) DB_HOST="$2"; shift ;; --db-port) DB_PORT="$2"; shift ;;
    --db-name) DB_NAME="$2"; shift ;; --db-user) DB_USER="$2"; shift ;; --admin) ADMIN="$2"; shift ;; --hub-dir) HUB_DIR="$2"; shift ;; --mail-host) MAIL_HOST="$2"; shift ;; --mail-port) MAIL_PORT="$2"; shift ;; --mail-user) MAIL_USER="$2"; shift ;; --mail-from) MAIL_FROM="$2"; shift ;;
    -h|--help) usage; exit 0 ;;
    *) bad "Unknown option: $1"; usage; exit 2 ;;
  esac; shift
done

say "${B}LinkHop${Z} installer"
say "${D}Free and open source. Nothing is sent anywhere; this script only runs on your machine.${Z}"
[ "$(id -u)" = 0 ] && warn "You are running as root. Not needed: the built-in server and the files work as a normal user."

# --- 1. Checks ---------------------------------------------------------------
hdr "Checking this computer"
PROBLEMS=0
need() { bad "$1"; say "     ${D}$2${Z}"; PROBLEMS=$((PROBLEMS + 1)); }
if have php; then
  PHPV="$(php -r 'echo PHP_VERSION;')"
  if version_ge "$PHPV" "8.2"; then ok "PHP $PHPV"; else need "PHP $PHPV is too old (need 8.2 or newer)" "macOS: brew install php   ·   Debian/Ubuntu: https://deb.sury.org   ·   Windows: use WSL"; fi
  MISSING=''
  MODS="$(php -m 2>/dev/null)"
  for e in mbstring openssl pdo tokenizer xml ctype fileinfo curl dom; do printf '%s\n' "$MODS" | grep -qi "^$e\$" || MISSING="$MISSING $e"; done
  [ -n "$MISSING" ] && need "PHP extensions missing:$MISSING" "Debian/Ubuntu: sudo apt install php-mbstring php-xml php-curl php-sqlite3 php-mysql   ·   macOS (brew) has them"
else
  need "PHP is not installed" "macOS: brew install php   ·   Debian/Ubuntu: sudo apt install php-cli php-mbstring php-xml php-curl php-sqlite3"
fi
have composer && ok "Composer $(composer --version 2>/dev/null | awk '{print $3}')" || need "Composer is not installed" "https://getcomposer.org/download/"
if have node && have npm; then
  NODEV="$(node -v | sed 's/^v//')"
  if version_ge "$NODEV" 20; then ok "Node $NODEV"; else need "Node $NODEV is too old (need 20+)" "https://nodejs.org or: nvm install 22"; fi
else need "Node.js and npm are not installed" "https://nodejs.org or: brew install node"; fi
have git && ok "git" || warn "git is not installed (only needed to download or update $APP)"
FREE="$(disk_free_mb "$SELF_DIR")"; [ "${FREE:-0}" -ge 1500 ] 2>/dev/null && ok "Disk: ${FREE} MB free" || warn "Less than 1.5 GB free (${FREE:-?} MB): the install needs about 1 GB"
[ "$PROBLEMS" -gt 0 ] && die "$PROBLEMS thing(s) to fix first (see above). Run this again when done; it is safe to repeat."

# --- 2. Questions ------------------------------------------------------------
hdr "A few questions (press Enter for the suggestion)"
if [ -f "$SELF_DIR/artisan" ] && [ -z "$DIR" ]; then DIR="$SELF_DIR"; fi
ask DIR "Install into which folder?" "$HOME/linkhop"
case "$DIR" in "~"*) DIR="$HOME${DIR#\~}" ;; esac
ask NAME "What do you want to call your site?" "LinkHop"
ask PORT "Port for the built-in server?" "8001"
case "$PORT" in ''|*[!0-9]*) die "Port must be a number" ;; esac
if [ -z "$URL" ] && [ -f "$DIR/.env" ]; then URL="$(get_env "$DIR/.env" APP_URL)"; [ -n "$URL" ] && say "  ${D}Keeping the address from the existing .env: $URL${Z}"; fi
ask URL "Public address of LinkHop?" "http://localhost:$PORT"
URL="${URL%/}"
if [ -z "${NO_HUB_QUESTION:-}" ]; then

  say ""
  say "  ${B}Accounts (HOP ID).${Z} LinkHop signs people in through a HOP hub:"
  say "    existing    = I already run a HOP ID hub (HEL.ink); connect to it"
  say "    new         = install a hub next to LinkHop now (best for a first try on this computer)"
  say "    standalone  = LinkHop with its own accounts, no hub (not available yet for LinkHop)"
  choose HUB "Which one?" new existing new standalone
  case "$HUB" in
    standalone) die "LinkHop does not have its own accounts yet; it uses a HOP hub for sign-in. Choose 'new' to run a private hub on this computer (one extra minute), or 'existing' to use one you already have." ;;
    existing)
      ask HUB_URL "Address of the hub (e.g. https://hub.example.com)?" ""
      [ -n "$HUB_URL" ] || die "The hub address is required with an existing hub"
      HUB_URL="${HUB_URL%/}"
      [ -n "$HUB_SECRET" ] || ask_secret HUB_SECRET "The shared app secret (HOP_SSO_SECRET in the hub's .env, hidden)?"
      [ "${#HUB_SECRET}" -ge 32 ] || die "The secret must be at least 32 characters (it is HOP_SSO_SECRET in the hub's .env)"
      say "  ${D}This app shares the hub's database: use the same database as the hub in the next question.${Z}" ;;
    new)
      DB=sqlite; DB_PATH="(the hub's file)"
      ask HUB_DIR "Install the hub into which folder?" "$(dirname "$DIR")/hel-hub" ;;
  esac

fi
choose DB "Database?" sqlite sqlite mysql
if [ "$DB" = mysql ]; then
  ask DB_HOST "MySQL/MariaDB host?" 127.0.0.1
  ask DB_PORT "MySQL/MariaDB port?" 3306
  ask DB_NAME "Database name?" "helink"
  ask DB_USER "Database user?" "helink"
  [ -n "$DB_PASS" ] || ask_secret DB_PASS "Database password (hidden)?"
  if have php && [ "$DRY" = 0 ]; then
    if P="$DB_PASS" php -r 'new PDO("mysql:host=".$argv[1].";port=".$argv[2].";dbname=".$argv[3],$argv[4],getenv("P"));' "$DB_HOST" "$DB_PORT" "$DB_NAME" "$DB_USER" 2>/dev/null; then ok "Connected to the database"; else die "Cannot connect to MySQL with those details. Create the database and user first, then run this again."; fi
  fi
else
  ask DB_PATH "SQLite file?" "$DIR/database/database.sqlite"
fi

ask ADMIN "Your e-mail (you become the owner)?" ""
if [ -z "$MAIL_HOST" ] && confirm "Set up outgoing e-mail now? (sign-up confirmation and password reset need it; you can do it later in .env)" n; then
  ask MAIL_HOST "SMTP host?" ""
fi
if [ -n "$MAIL_HOST" ]; then
  ask MAIL_PORT "SMTP port?" 587
  ask MAIL_USER "SMTP user name?" ""
  [ -n "$MAIL_PASS" ] || ask_secret MAIL_PASS "SMTP password (hidden)?"
  ask MAIL_FROM "Send e-mail from which address?" "${ADMIN:-hello@${MAIL_HOST#*.}}"
fi
choose RUN "How will it run?" serve serve none

# --- 3. Summary --------------------------------------------------------------
hdr "Summary"
say "  Site name   $NAME"
say "  Folder      $DIR"
say "  Address     $URL   (built-in server on port $PORT)"
say "  Accounts    $([ "$HUB" = new ] && echo "a new hub in $HUB_DIR" || echo "existing hub $HUB_URL")"
say "  Database    $DB${DB_PATH:+ ($DB_PATH)}"
say "  Owner       ${ADMIN:-(not set)}"
say "  E-mail      ${MAIL_HOST:-not set up (confirmation links are written to storage/logs instead)}"
say "  Run         $RUN"
confirm "Install now?" y || { say "Nothing was changed."; exit 0; }
[ "$DRY" = 1 ] && say "${Y}Dry run: nothing below is executed.${Z}"

# --- 4. Get the code (when run from a download) ---------------------------------
if [ ! -f "$DIR/artisan" ]; then
  have git || die "git is needed to download LinkHop"
  step "Downloading LinkHop into $DIR" git clone --depth 1 https://github.com/navi-crwn/LinkHop.git "$DIR"
fi
cd "$DIR" 2>/dev/null || { [ "$DRY" = 1 ] && exit 0; die "Cannot enter $DIR"; }


if [ "$HUB" = new ]; then
  hdr "Installing the hub first (HEL.ink)"
  if [ -f "$HUB_DIR/.env" ] && [ -f "$HUB_DIR/artisan" ]; then
    HUB_URL="$(get_env "$HUB_DIR/.env" APP_URL)"
    ok "A hub is already installed in $HUB_DIR ($HUB_URL): using it"
  else
    HUB_PORT="$(free_port 8000)"; [ "$HUB_PORT" = "$PORT" ] && HUB_PORT="$(free_port $((PORT + 1)))"
    HUB_URL="http://localhost:$HUB_PORT"
    if [ ! -f "$HUB_DIR/artisan" ]; then step "Downloading HEL.ink into $HUB_DIR" git clone --depth 1 https://github.com/navi-crwn/HEL.ink.git "$HUB_DIR"; fi
    step "Installing the hub" bash "$HUB_DIR/install.sh" --yes --no-star --dir "$HUB_DIR" --name "HOP" --url "$HUB_URL" --port "$HUB_PORT" --db sqlite --admin "$ADMIN"
  fi
  if [ "$DRY" = 0 ]; then
    HUB_SECRET="$(get_env "$HUB_DIR/.env" HOP_SSO_SECRET)"
    DB=sqlite; DB_PATH="$(get_env "$HUB_DIR/.env" DB_DATABASE)"
    [ -n "$HUB_SECRET" ] || die "The hub did not create a secret"
  fi
  hdr "Back to LinkHop"
fi

# --- 5. Install --------------------------------------------------------------
hdr "Installing"
step "PHP packages (composer)" composer install --no-dev --optimize-autoloader --no-interaction --no-progress
step "Front end (npm)" npm ci --no-audit --no-fund --loglevel=error
step "Building the front end" npm run build --silent

if [ -f .env ] && [ "$DRY" = 0 ]; then
  if confirm ".env already exists. Keep it and only add what is missing?" y; then KEEP_ENV=1; else cp .env ".env.backup-$(date +%Y%m%d%H%M%S)"; KEEP_ENV=0; ok "Old .env saved next to it"; fi
else KEEP_ENV=0; fi
if [ "$KEEP_ENV" = 0 ] && [ "$DRY" = 0 ]; then
  cp .env.example .env
  set_env .env APP_NAME "$NAME"
  set_env .env APP_URL "$URL"
  # The apps force https in the "production" environment, so a plain http address (a trial on this computer) runs as "local".
  case "$URL" in https://*) set_env .env APP_ENV production ;; *) set_env .env APP_ENV local ;; esac
  set_env .env APP_DEBUG false
  case "$URL" in https://*) set_env .env SESSION_SECURE_COOKIE true ;; *) set_env .env SESSION_SECURE_COOKIE false ;; esac
  set_env .env TRUSTED_PROXIES ""
  set_env .env LOG_CHANNEL daily
  [ -n "$ADMIN" ] && set_env .env SUPERADMIN_EMAIL "$ADMIN"
  [ -n "$ADMIN" ] && set_env .env ADMIN_EMAIL "$ADMIN"
  if [ -n "$MAIL_HOST" ]; then
    set_env .env MAIL_MAILER smtp; set_env .env MAIL_HOST "$MAIL_HOST"; set_env .env MAIL_PORT "$MAIL_PORT"
    set_env .env MAIL_USERNAME "$MAIL_USER"; set_env .env MAIL_PASSWORD "$MAIL_PASS"; set_env .env MAIL_FROM_ADDRESS "$MAIL_FROM"
  else
    # No mail server yet: messages go to the log, so the debug level is needed for them to show up.
    set_env .env MAIL_MAILER log; set_env .env LOG_LEVEL debug
  fi
  HOSTONLY="$(printf '%s' "$URL" | sed -E 's#^[a-z]+://##; s#[:/].*$##')"

  set_env .env PUBLIC_URL "$URL"
  set_env .env SESSION_COOKIE linkhop_session
  [ "$DB" = sqlite ] && set_env .env DB_MIGRATIONS_TABLE migrations
  set_env .env LEGACY_SLUG_UNTIL ""
  set_env .env BIOHOP_ENABLED false; set_env .env PIXELHOP_ENABLED false
  set_env .env LINKHOP_URL "$URL"

  if [ "$DB" = sqlite ]; then
    [ -n "$DB_PATH" ] || DB_PATH="$DIR/database/database.sqlite"
    mkdir -p "$(dirname "$DB_PATH")"; [ -f "$DB_PATH" ] || : >"$DB_PATH"
    set_env .env DB_CONNECTION sqlite
    set_env .env DB_DATABASE "$DB_PATH"
  else
    set_env .env DB_CONNECTION mysql
    set_env .env DB_HOST "$DB_HOST"; set_env .env DB_PORT "$DB_PORT"; set_env .env DB_DATABASE "$DB_NAME"
    set_env .env DB_USERNAME "$DB_USER"; set_env .env DB_PASSWORD "$DB_PASS"
  fi
  chmod 600 .env
  ok "Wrote .env (private to you; never committed)"
fi
if [ "$DRY" = 0 ]; then
  set_env .env HOP_APP linkhop
  set_env .env HOP_HUB_URL "$HUB_URL"
  set_env .env HOP_SSO_SECRET "$HUB_SECRET"
fi
grep -qE '^APP_KEY=.+' .env 2>/dev/null || step "Creating the app key" php artisan key:generate --force
step "Creating the database tables" php artisan migrate --force
step "Linking the storage folder" php artisan storage:link --force
step "Warming caches" php artisan optimize

if [ "$HUB" = new ] && [ "$DRY" = 0 ] && [ -f "$HUB_DIR/.env" ]; then
  set_env "$HUB_DIR/.env" LINKHOP_URL "$URL"; set_env "$HUB_DIR/.env" LINKHOP_ENABLED true
  (cd "$HUB_DIR" && php artisan config:clear >/dev/null 2>&1 && php artisan optimize >/dev/null 2>&1) && ok "Registered LinkHop in the hub's list of apps"
fi


# --- 6. How to keep it running -------------------------------------------------
[ "$DRY" = 1 ] || cat >start.sh <<START
#!/usr/bin/env bash
# Start LinkHop: the web server, the queue worker and the scheduler. Stop with Ctrl+C.
cd "\$(dirname "\$0")"
php artisan serve --host=127.0.0.1 --port=$PORT &
php artisan queue:work --tries=3 --sleep=3 &
php artisan schedule:work &
trap 'kill 0' INT TERM
wait
START
[ "$DRY" = 1 ] || chmod +x start.sh

hdr "Done"
if [ "$DRY" = 1 ]; then ok "Dry run finished: nothing was installed"; else ok "LinkHop is installed in $DIR"; fi
say ""
say "  ${B}Start it:${Z}   cd $DIR && ./start.sh"
say "  ${B}Open it:${Z}    $URL"
if [ "$HUB" = new ]; then
  say "  ${B}Also start the hub:${Z}  cd $HUB_DIR && ./start.sh      (sign in at $HUB_URL with ${ADMIN:-your e-mail}, then open LinkHop)"
else
  say "  ${B}At the hub:${Z} add $URL to the apps list (LINKHOP_URL=$URL, LINKHOP_ENABLED=true in the hub's .env), then run php artisan optimize there."
fi
say ""
say "  ${B}Behind nginx (your own domain):${Z} point the web root at $DIR/public, use PHP-FPM, and run these two"
say "  in the background (a systemd service or supervisor):"
say "    php artisan queue:work --tries=3        and        php artisan schedule:work"
say "  or the single cron line:  * * * * * cd $DIR && php artisan schedule:run >> /dev/null 2>&1"
say "  Update later with ./update.sh · remove with ./uninstall.sh"
say ""
if [ "$DRY" = 0 ]; then star_prompt; fi

}
main "$@"; exit $?
