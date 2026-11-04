#!/usr/bin/env bash
# The family loads no script or font from a CDN. Fails on third-party script/style hosts in the views, and on a running copy's /up page.
#   scripts/check-no-cdn.sh [http://127.0.0.1:8000]
set -euo pipefail
cd "$(dirname "$0")/.."
bad=$(grep -rnE '(cdn\.jsdelivr\.net|unpkg\.com|cdnjs\.cloudflare\.com|fonts\.bunny\.net|fonts\.googleapis\.com|fonts\.gstatic\.com)' resources packages/*/resources 2>/dev/null | grep -v 'Content-Security' || true)
[[ -z "$bad" ]] || { echo "::error::CDN reference in views:"; echo "$bad"; exit 1; }
if [[ -n "${1:-}" ]]; then
  body=$(curl -fsS "$1/up")
  if grep -qE 'cdn\.jsdelivr\.net|fonts\.bunny\.net' <<<"$body"; then echo "::error::$1/up is the framework default page and loads a CDN script/font"; exit 1; fi
fi
echo "ok: no CDN references"
