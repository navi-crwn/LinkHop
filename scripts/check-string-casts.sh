#!/usr/bin/env bash
# (string) $request->query('x') / ->input('x') turns ?x[]=1 into "Array to string conversion" = HTTP 500.
# Lists every such cast outside the signed server-to-server API; use is_string() first, or $request->string()->value() after validation.
set -euo pipefail
cd "$(dirname "$0")/.."
hits=$(grep -rnE '\(string\) *\$request->(query|input|get)\(' app packages/*/src 2>/dev/null | grep -v 'Api/Hop\|Internal/' || true)
if [[ -n "$hits" ]]; then echo "$hits"; echo "::warning::$(wc -l <<<"$hits" | tr -d ' ') unguarded (string) casts of request values"; [[ "${1:-}" == "--strict" ]] && exit 1; fi
echo "done"
