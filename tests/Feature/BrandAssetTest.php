<?php

use App\Models\User;

test('pages link brand images by a version that changes with the files', function () {
    $html = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->getContent();
    expect($html)->toMatch('#/brand/[a-f0-9]{8}/favicon\.png#')
        ->toMatch('#/brand/[a-f0-9]{8}/linkhop-wordmark\.svg#');

    preg_match('#/brand/([a-f0-9]{8})/favicon\.png#', $html, $m);
    $this->get("/brand/{$m[1]}/favicon.png")->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    $this->get("/brand/{$m[1]}/linkhop-wordmark.svg")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
});

test('every file in resources/brand is served, nothing outside it is', function () {
    foreach (glob(resource_path('brand').'/*.{png,svg}', GLOB_BRACE) as $file) {
        $this->get(route('brand.asset', basename($file)))->assertOk();
    }
    $this->get('/brand/0123abcd/nope.png')->assertNotFound();
    $this->get('/brand/0123abcd/README.md')->assertNotFound();
});

test('the old unversioned brand paths still work but are cached only briefly', function () {
    foreach (['/brand/favicon.png', '/brand/logo.png', '/brand/logo-dark.png'] as $path) {
        $this->get($path)->assertOk()->assertHeader('Cache-Control', 'max-age=3600, public');
    }
});
