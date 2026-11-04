#!/usr/bin/env bash
# A migration added on this branch must sort AFTER every migration already on the base branch. Names dated in the
# future (e.g. 2026_11_08_*) make a later, earlier-dated file run first on a fresh install and fail when it needs a
# column the future-dated one adds.
#
#   scripts/check-migration-order.sh [base-ref]     (default origin/main)
set -euo pipefail
cd "$(dirname "$0")/.."
base=${1:-origin/main}
dirs=(database/migrations packages/bio/database/migrations)
last=$(for d in "${dirs[@]}"; do git ls-tree --name-only "$base" "$d/" 2>/dev/null; done | xargs -n1 basename 2>/dev/null | grep -E '^[0-9_]+' | sort | tail -1)
fail=0
for f in $(git diff --name-only --diff-filter=A "$base"...HEAD -- "${dirs[@]}" 2>/dev/null); do
  n=$(basename "$f")
  if [[ "$n" < "$last" ]]; then echo "::error file=$f::$n sorts before $last, which is already on $base"; fail=1; fi
done
[[ $fail == 0 ]] && echo "ok: new migrations sort after $last"
exit $fail
