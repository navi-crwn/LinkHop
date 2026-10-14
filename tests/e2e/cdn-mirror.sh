#!/usr/bin/env bash
# Copy every CDN script the views load (unpkg / jsdelivr, pinned versions) into a
# local folder, for running the synthetic-user suite without internet:
#   bash tests/e2e/cdn-mirror.sh /tmp/cdn && E2E_CDN_MIRROR=/tmp/cdn npx playwright test -c tests/e2e
set -euo pipefail
cd "$(dirname "$0")/../.."
OUT="$(realpath -m "${1:?usage: cdn-mirror.sh <dir>}")"
mkdir -p "$OUT"
grep -rhoE 'https://(unpkg\.com|cdn\.jsdelivr\.net/npm)/[^"'"'"' ]+' resources/views \
  | sed -E 's#https://(unpkg\.com|cdn\.jsdelivr\.net/npm)/##' | grep -oE '^(@[^/]+/)?[^/@]+@[^/]+' | sort -u \
  | while read -r spec; do
      [ -d "$OUT/$spec" ] && continue
      tmp="$(mktemp -d)"
      (cd "$tmp" && npm pack "$spec" --silent >/dev/null && mkdir -p "$OUT/$spec" && tar -xzf ./*.tgz -C "$OUT/$spec" --strip-components=1)
      rm -rf "$tmp"
      echo "mirrored $spec"
    done
