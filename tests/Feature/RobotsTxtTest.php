<?php

/*
| robots.txt names pages that exist, keeps the signed-in areas out of search results, and points at the sitemap.
*/

/** @return array{allow: list<string>, disallow: list<string>, sitemap: list<string>} */
function robotsRules(): array
{
    $rules = ['allow' => [], 'disallow' => [], 'sitemap' => []];
    foreach (preg_split('/\R/', (string) file_get_contents(public_path('robots.txt'))) as $line) {
        if (preg_match('/^(Allow|Disallow|Sitemap):\s*(\S+)/i', $line, $m)) {
            $rules[strtolower($m[1])][] = $m[2];
        }
    }

    return $rules;
}

it('allows only pages that exist', function () {
    foreach (robotsRules()['allow'] as $path) {
        if ($path === '/' || str_ends_with($path, '/')) {
            continue; // the home page and the bio prefix
        }
        expect($this->get($path)->status())->not->toBe(404, "Allow: {$path} is a 404");
    }
});

it('keeps the signed-in areas out of search results', function () {
    $disallowed = robotsRules()['disallow'];
    foreach (['/dashboard', '/links', '/bio', '/folders', '/tags', '/analytics', '/api-docs', '/settings', '/api/', '/login'] as $path) {
        expect($disallowed)->toContain($path);
    }
    // the public bio pages (/b/…) are not under a disallowed prefix
    expect(collect($disallowed)->contains(fn ($d) => str_starts_with('/b/x', $d)))->toBeFalse();
});

it('points at the sitemap', function () {
    // this file is served by the app's own host, and that is where its sitemap is; the hub's host has no such page
    expect(robotsRules()['sitemap'])->toBe(['https://link.hel.ink/sitemap.xml']);
    $this->get('/sitemap.xml')->assertOk();
});

it('lists only URLs on the host that serves the sitemap', function () {
    config(['app.url' => 'https://link.hel.ink', 'shortener.public_url' => 'https://hel.ink']);
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    preg_match_all('~<loc>([^<]+)</loc>~', $xml, $m);

    expect($m[1])->toContain('https://link.hel.ink/', 'https://link.hel.ink/qr', 'https://link.hel.ink/faq');
    foreach ($m[1] as $loc) {
        expect(parse_url($loc, PHP_URL_HOST))->toBe('link.hel.ink');
    }
    expect(file_get_contents(public_path('robots.txt')))->not->toContain('Last updated');
});
