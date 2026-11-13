<?php

use App\Models\User;

/*
| A page whose inline <script> has a syntax error loses the whole block, and with it everything the block does
| (on analytics: the chart, the map and the country focus). String assertions on the HTML cannot see that, so this
| test renders the member pages, takes every executable inline script and has Node parse it. It fails, it does not
| skip, when Node is missing: a silent skip is how a broken page got through before.
*/

function inlineScriptsOf(string $html): array
{
    preg_match_all('#<script\b([^>]*)>(.*?)</script>#si', $html, $m, PREG_SET_ORDER);
    $scripts = [];
    foreach ($m as $i => $hit) {
        $attrs = $hit[1];
        if (preg_match('/\bsrc\s*=/i', $attrs) === 1) {
            continue;
        }
        if (preg_match('/\btype\s*=\s*["\']?([^"\'\s>]+)/i', $attrs, $t) === 1
            && ! in_array(strtolower($t[1]), ['text/javascript', 'application/javascript', 'module'], true)) {
            continue; // JSON data blocks and the like
        }
        if (trim($hit[2]) !== '') {
            $scripts[$i] = $hit[2];
        }
    }

    return $scripts;
}

function nodeBinary(): string
{
    $bin = trim((string) (getenv('NODE_BINARY') ?: ''));
    if ($bin === '') {
        $bin = 'node';
    }
    exec(escapeshellarg($bin).' --version 2>&1', $out, $code);
    if ($code !== 0) {
        throw new RuntimeException('Node is required to parse the inline scripts (set NODE_BINARY): '.implode(' ', $out));
    }

    return $bin;
}

test('node is available to parse inline scripts', function () {
    expect(nodeBinary())->not->toBe('');
});

test('every inline script on the member pages parses', function (string $path) {
    $node = nodeBinary();
    $user = User::factory()->create();
    $html = $this->actingAs($user)->get($path)->assertOk()->getContent();
    $scripts = inlineScriptsOf($html);
    expect($scripts)->not->toBeEmpty();

    $problems = [];
    foreach ($scripts as $i => $code) {
        $file = tempnam(sys_get_temp_dir(), 'hopjs').'.js';
        file_put_contents($file, $code);
        exec(escapeshellarg($node).' --check '.escapeshellarg($file).' 2>&1', $out, $status);
        @unlink($file);
        if ($status !== 0) {
            $problems[] = "script #{$i} on {$path}: ".trim(implode("\n", array_slice($out, 0, 6)));
        }
        $out = [];
    }
    expect($problems)->toBe([]);
})->with(['/analytics', '/dashboard', '/links']);
