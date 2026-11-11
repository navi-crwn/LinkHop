#!/usr/bin/env bash
# Check that README.md carries the canonical HOP family table, unchanged, between
# <!-- hop-family:start --> and <!-- hop-family:end -->. The canonical copy is
# docs/hop-family.md in navi-crwn/HEL.ink (generated from its asset manifest).
#
#   scripts/check-hop-family.sh                    # check against HEL.ink main
#   scripts/check-hop-family.sh --fix              # replace the README block with the canonical one
#   scripts/check-hop-family.sh --ref <branch|sha> # use that HEL.ink ref instead of main (or HOP_FAMILY_REF=<ref>)
#   scripts/check-hop-family.sh --from <file>      # use a local copy of docs/hop-family.md (or HOP_FAMILY_FILE=<file>)
# Options combine, e.g. --ref feat/x --fix.
set -euo pipefail
cd "$(dirname "$0")/.."
fix=0; ref="${HOP_FAMILY_REF:-main}"; from="${HOP_FAMILY_FILE:-}"
while [[ $# -gt 0 ]]; do
  case "$1" in
    --fix) fix=1 ;;
    --ref) ref="${2:?--ref needs a branch or sha}"; shift ;;
    --from) from="${2:?--from needs a file}"; shift ;;
    *) echo "unknown option: $1" >&2; exit 2 ;;
  esac
  shift
done
url=https://raw.githubusercontent.com/navi-crwn/HEL.ink/$ref/docs/hop-family.md
if [[ -n "$from" ]]; then
  [[ -f "$from" ]] || { echo "::error::$from does not exist"; exit 2; }
  canon=$(cat "$from")
elif [[ -f docs/hop-family.md && -f scripts/hop_assets.py ]]; then
  canon=$(cat docs/hop-family.md)              # HEL.ink itself
elif ! canon=$(curl -fsS --max-time 10 "$url"); then
  echo "::warning::could not fetch $url; skipping the HOP family check"
  exit 0
fi
block=$(sed -n '/<!-- hop-family:start -->/,/<!-- hop-family:end -->/p' README.md)
if [[ "$fix" == 1 ]]; then
  CANON="$canon" python3 - <<'PY'
import os, re, pathlib
p = pathlib.Path("README.md"); s = p.read_text()
new = re.sub(r"<!-- hop-family:start -->.*?<!-- hop-family:end -->", lambda m: os.environ["CANON"].strip(), s, flags=re.S)
if new == s and "<!-- hop-family:start -->" not in s:
    raise SystemExit("README.md has no hop-family markers")
p.write_text(new)
PY
  exit 0
fi
if [[ "$block" != "$canon" ]]; then
  echo "::error file=README.md::the HOP family table differs from ${from:-$url} (run scripts/check-hop-family.sh --fix)"
  diff <(echo "$canon") <(echo "$block") || true
  exit 1
fi
echo "HOP family table OK"
