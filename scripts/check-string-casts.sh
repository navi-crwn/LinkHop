#!/usr/bin/env bash
# (string) $request->query('x') / ->input('x') turns ?x[]=1 into "Array to string conversion" = HTTP 500.
# $request->string('x') and ->str('x') do the same cast inside, so they count too.
# Lists every such call outside the signed server-to-server API; use is_string() first, or $this->textInput() in a controller.
set -euo pipefail
cd "$(dirname "$0")/.."
hits=$(grep -rnE '\(string\) *\$request->(query|input|get)\(|(\$request|request\(\))->(string|str)\(' ${CHECK_DIRS:-app packages/*/src} 2>/dev/null | grep -v 'Api/Hop\|Internal/' || true)
if [[ -n "$hits" ]]; then echo "$hits"; echo "::warning::$(wc -l <<<"$hits" | tr -d ' ') unguarded casts of request values"; [[ "${1:-}" == "--strict" ]] && exit 1; fi
echo "done"
