<?php

use Hop\Ui\Assets;

test('the home page has every scene, complete as plain HTML', function () {
    $html = $this->get('/')->assertOk()->getContent();
    foreach (['link-tour', 'journey', 'share', 'qr', 'analytics', 'control', 'routing', 'protect', 'api', 'open', 'faq', 'family'] as $id) {
        expect($html)->toContain('id="'.$id.'"');
    }
    // the long address and the finished short link are both in the markup: without script nothing is hidden
    expect($html)->toContain('data-lx-long')->toContain('data-lx-result')->toContain('hel.ink/l/');
    // the scenes only use features the app really has
    expect($html)->toContain('data-lx-qrdeck')->toContain('Illustrative analytics');
});

test('the QR scene has four codes in turn, the link code drawn on the server, and a way to the QR studio', function () {
    $html = $this->get('/')->assertOk()->getContent();
    preg_match('~<section id="qr".*?</section>~s', $html, $m);
    $section = $m[0] ?? '';

    expect(substr_count($section, 'data-qd-card'))->toBe(4)->and(substr_count($section, 'data-qd-tab'))->toBe(4)
        ->and($section)->toContain('data-scene')->toContain('href="'.route('qr').'"')->toContain('data-lib=')
        // a version 3 code has 29 x 29 modules; roughly half of them are dark: the path holds one square per dark module
        ->and(substr_count($section, 'h1v1h-1z'))->toBeGreaterThan(300)
        ->and($section)->not->toContain('data-lx-vox');
    // the hero keeps its small flat code
    expect($html)->toContain('lx-qr-flat');
});

test('the timing, routing and protect scenes describe features the app really has', function () {
    $html = $this->get('/')->assertOk()->getContent();
    foreach (['data-lx-control', 'data-lx-routing', 'data-lx-gate', 'Idempotency-Key', 'X-LinkHop-Signature'] as $needle) {
        expect($html)->toContain($needle);
    }
});

test('the other apps of the family appear as 3D marks with the flat mark behind them', function () {
    $html = $this->get('/')->assertOk()->getContent();
    $others = collect(Assets::family('linkhop'))->reject(fn ($app) => $app['current'])->pluck('slug');
    expect($others)->toContain('payhop')->not->toContain('linkhop')
        ->and(substr_count($html, 'data-hop-hero="'))->toBe($others->count())->and($html)->toContain('data-lk-family');
    foreach ($others as $slug) {
        expect($html)->toContain('data-hop-hero="'.$slug.'"');
    }
    expect($html)->not->toContain('data-hop-hero="linkhop"');
});

test('the home engine files are requested with a content hash, so an edited file is not served from an old cache', function () {
    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toMatch('#hop-home/hop-hero3d\.js\?v=[0-9a-f]{8}#')->and($html)->toMatch('#hop-home/hop-motion\.js\?v=[0-9a-f]{8}#');
});

test('GSAP and Lenis come from our own bundle, never from a CDN', function () {
    $js = file_get_contents(base_path('resources/js/home.js'));
    expect($js)->toContain("from 'gsap'")->toContain("from 'lenis'")->not->toContain('cdn');
    $html = $this->get('/')->getContent();
    expect($html)->not->toContain('cdnjs')->not->toContain('unpkg.com/gsap');
});

test('the engine in the shared home folder animates every canvas in a row (an unsettled scene is never held back by the limit of four)', function () {
    expect(file_get_contents(public_path('hop-home/hop-hero3d.js')))->toContain('return !s.settled;')->toContain('.slice(0, Math.max(0, 4 - live.length))');
});

test('a family app the 3D engine has no block map for keeps its flat mark instead of borrowing another logo', function () {
    expect(file_get_contents(public_path('hop-home/hop-hero3d.js')))->toContain('if (!MAPS[preset]) { lazy.unobserve(entry.target); return; }');
});

test('top bar buttons keep their text on one line, and the bars fit a 320 px phone without squeezing the logo', function () {
    $nav = file_get_contents(base_path('resources/views/partials/marketing-nav.blade.php'));
    $public = file_get_contents(base_path('resources/views/components/public-layout.blade.php'));
    expect($nav)->toContain('whitespace-nowrap rounded-xl bg-slate-900')->toContain('flex shrink-0 items-center')->toContain('max-[380px]:hidden');
    expect($public)->toContain('whitespace-nowrap rounded-xl bg-white')->not->toContain('<x-hop::badge />');
});

test('card grids on the group, API and analytics pages cannot push the page wider than the screen', function () {
    expect(file_get_contents(base_path('resources/views/partials/groups/index.blade.php')))->toContain('grid grid-cols-1 gap-3');
    expect(file_get_contents(base_path('resources/views/api-docs.blade.php')))->toContain('grid grid-cols-1 gap-3 md:grid-cols-2');
    expect(file_get_contents(base_path('resources/views/analytics.blade.php')))->toContain('glass-card min-w-0');
});
