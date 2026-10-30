#!/usr/bin/env bash
# Check that README.md carries the canonical HOP family table, unchanged, between
# <!-- hop-family:start --> and <!-- hop-family:end -->. The canonical copy is
# docs/hop-family.md in navi-crwn/HEL.ink (generated from its asset manifest).
#
#   scripts/check-hop-family.sh          # check
#   scripts/check-hop-family.sh --fix    # replace the README block with the canonical one
set -euo pipefail
cd "$(dirname "$0")/.."
url=https://raw.githubusercontent.com/navi-crwn/HEL.ink/main/docs/hop-family.md
if [[ -f docs/hop-family.md && -f scripts/hop_assets.py ]]; then
  canon=$(cat docs/hop-family.md)              # HEL.ink itself
elif ! canon=$(curl -fsS --max-time 10 "$url"); then
  echo "::warning::could not fetch $url; skipping the HOP family check"
  exit 0
fi
block=$(sed -n '/<!-- hop-family:start -->/,/<!-- hop-family:end -->/p' README.md)
if [[ "${1:-}" == "--fix" ]]; then
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
  echo "::error file=README.md::the HOP family table differs from $url (run scripts/check-hop-family.sh --fix)"
  diff <(echo "$canon") <(echo "$block") || true
  exit 1
fi
echo "HOP family table OK"
