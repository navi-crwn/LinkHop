#!/usr/bin/env bash
# Pages behind a session must answer Cache-Control: no-store (Back after logout must not show cached account pages).
# Needs a running local copy and a Cookie header of a signed-in session: COOKIE='name=value; ...' scripts/check-cache-control.sh http://127.0.0.1:8000
set -euo pipefail
base=${1:?usage: check-cache-control.sh <base-url>}
paths=${PATHS:-"/dashboard /settings /bio"}
fail=0
for p in $paths; do
  cc=$(curl -s -o /dev/null -D - -H "Cookie: ${COOKIE:?set COOKIE}" "$base$p" | tr -d '\r' | awk -F': ' 'tolower($1)=="cache-control"{print $2}')
  if [[ "$cc" != *no-store* ]]; then echo "::error::$p answers Cache-Control: ${cc:-<none>} (want no-store)"; fail=1; else echo "ok $p: $cc"; fi
done
exit $fail
