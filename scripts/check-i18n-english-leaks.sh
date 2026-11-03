#!/usr/bin/env bash
# Fetches public pages of a running copy in a non-Latin language and lists visible English sentences (strings that skipped __()).
#   scripts/check-i18n-english-leaks.sh http://127.0.0.1:8000 [ja] [--strict]
set -euo pipefail
base=${1:?usage: check-i18n-english-leaks.sh <base-url> [lang]}; lang=${2:-ja}
paths=${PATHS:-"/ /features /faq /privacy /terms /help /status /about /contact /report-abuse"}
total=0
for p in $paths; do
  html=$(curl -fsS -H "Cookie: hop_lang=$lang" "$base$p?lang=$lang" || true)
  out=$(php -r '
    libxml_use_internal_errors(true);
    $d = new DOMDocument(); $d->loadHTML("<?xml encoding=\"UTF-8\">".stream_get_contents(STDIN));
    foreach (["script", "style", "svg", "pre", "code", "noscript", "textarea"] as $t) { foreach (iterator_to_array($d->getElementsByTagName($t)) as $n) { $n->parentNode->removeChild($n); } }
    $x = new DOMXPath($d); $seen = [];
    foreach ($x->query("//body//text()") as $n) {
        $s = trim(preg_replace("/\s+/", " ", $n->nodeValue));
        if (preg_match("/^[A-Za-z0-9 ,.\x27:;!?()\/&-]+$/", $s) && preg_match("/[A-Za-z]{3,}( [A-Za-z\x27]{2,}){2,}/", $s) && ! isset($seen[$s])) { $seen[$s] = 1; echo substr($s, 0, 110), "\n"; }
    }' <<<"$html")
  if [[ -n "$out" ]]; then echo "== $p ($lang)"; echo "$out" | sed 's/^/   /'; total=$((total + $(wc -l <<<"$out"))); fi
done
echo "$total English sentence(s) on $lang pages"
[[ "${3:-}" == "--strict" && "$total" -gt 0 ]] && exit 1 || exit 0
