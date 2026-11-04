#!/usr/bin/env bash
# Fail when code calls route('name') / to_route('name') / ->route('name') for a name that is not registered
# (such a call throws RouteNotFoundException = HTTP 500 when the page renders).
#
#   scripts/check-route-names.sh
set -euo pipefail
cd "$(dirname "$0")/.."
tmp=$(mktemp); trap 'rm -f "$tmp"' EXIT
php artisan route:list --json > "$tmp"
php -r '
$names = array_filter(array_column(json_decode(file_get_contents($argv[1]), true), "name"));
$missing = [];
foreach (["app", "resources", "packages", "routes", "config", "bootstrap"] as $dir) {
    if (! is_dir($dir)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $path = $file->getPathname();
        if (! preg_match("/\.php$/", $path) || str_contains($path, "/tests/") || str_contains($path, "/vendor/")) { continue; }
        foreach (file($path) as $i => $line) {
            // route(...) as a function or a redirect()/to_route() helper; $this->route("x") is a request parameter and is skipped
            if (preg_match_all("/(?<![>\w$])(?:route|to_route)\(\s*[\x27\"]([A-Za-z0-9_.\-]+)[\x27\"]/", $line, $m)
                || preg_match_all("/redirect\(\)->route\(\s*[\x27\"]([A-Za-z0-9_.\-]+)[\x27\"]/", $line, $m)) {
                foreach ($m[1] as $n) { if (! in_array($n, $names, true)) { $missing[] = "$path:".($i + 1)." route(\x27$n\x27)"; } }
            }
        }
    }
}
if ($missing) { fwrite(STDERR, "::error::unregistered route names:\n".implode("\n", $missing)."\n"); exit(1); }
echo "ok: every route name used in code is registered (".count($names)." routes)\n";
' "$tmp"
