#!/usr/bin/env bash
# Lists fetch() calls whose next 30 lines never look at response.ok / res.status (a 4xx/5xx would then be treated as success).
# Prints file:line for each; exits 1 with --strict. Known-benign sites can be listed in scripts/fetch-ok-allowlist.txt (file:line, one per line).
set -euo pipefail
cd "$(dirname "$0")/.."
allow=scripts/fetch-ok-allowlist.txt; [[ -f $allow ]] || allow=/dev/null
hits=0
while IFS= read -r f; do
  awk -v F="$f" '
    { L[NR]=$0 }
    END { for (i=1;i<=NR;i++) if (L[i] ~ /(^|[^A-Za-z_.])fetch\(/ && L[i] !~ /Http::/) {
        ok=0; for (j=i;j<=i+30&&j<=NR;j++) if (L[j] ~ /\.ok([^A-Za-z]|$)|\.status[ ]*[=!<>]|response\.status|res\.status/) { ok=1; break }
        if (!ok) print F ":" i } }' "$f"
done < <(find resources packages -type f \( -name '*.js' -o -name '*.blade.php' \) -not -path '*/node_modules/*' -not -path '*/tests/*' 2>/dev/null) | sort | while read -r site; do
  grep -qxF "$site" "$allow" && continue
  echo "::warning file=${site%%:*},line=${site##*:}::fetch() without a response.ok/status check"; echo "$site" >> /tmp/.fetch-ok-hits.$$
done
n=$(wc -l < /tmp/.fetch-ok-hits.$$ 2>/dev/null || echo 0); rm -f /tmp/.fetch-ok-hits.$$
echo "$n fetch() call(s) without a status check"
[[ "${1:-}" == "--strict" && "$n" -gt 0 ]] && exit 1 || exit 0
