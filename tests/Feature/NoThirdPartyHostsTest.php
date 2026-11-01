<?php

use App\Models\Link;
use App\Models\User;

// HOP does not call third-party hosts from its pages: libraries are self-hosted (public/vendor), flags are local.
const THIRD_PARTY = ['cdn.jsdelivr.net', 'unpkg.com', 'cdn.tailwindcss.com', 'flagcdn.com', 'fonts.googleapis.com', 'fonts.gstatic.com', 'cdn.simpleicons.org', 'code.jquery.com', 'cdnjs.cloudflare.com', 'raw.githubusercontent.com'];

test('the signed-in layout loads its libraries from our own files', function () {
    $user = User::factory()->create();
    $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
    foreach (THIRD_PARTY as $host) {
        expect($html)->not->toContain($host);
    }
    foreach (['chartjs/chart.umd.min.js', 'chartjs/chartjs-plugin-zoom.min.js', 'leaflet/leaflet.js', 'leaflet/leaflet.css', 'qr-code-styling/qr-code-styling.js'] as $file) {
        expect($html)->toContain('vendor/'.$file)->and(file_exists(public_path('vendor/'.$file)))->toBeTrue();
    }
});

test('the vendored libraries are the pinned versions, with their licences next to them', function () {
    expect(file_get_contents(public_path('vendor/chartjs/chart.umd.min.js')))->toContain('Chart.js v4.4.0');
    expect(file_get_contents(public_path('vendor/leaflet/leaflet.js')))->toContain('1.9.4');
    expect(file_exists(public_path('vendor/leaflet/images/marker-icon.png')))->toBeTrue();
    foreach (['chartjs/LICENSE-chart.js.md', 'leaflet/LICENSE', 'qr-code-styling/LICENSE', 'alpine/LICENSE.md'] as $licence) {
        expect(file_exists(public_path('vendor/'.$licence)))->toBeTrue();
    }
});

test('the analytics map reads its country outlines from our own file', function () {
    $user = User::factory()->create();
    $html = $this->actingAs($user)->get('/analytics')->assertOk()->getContent();
    expect($html)->toContain('vendor\\/geo\\/countries.geo.json')->not->toContain('raw.githubusercontent.com');
    expect(json_decode(file_get_contents(public_path('vendor/geo/countries.geo.json')), true)['features'])->not->toBeEmpty();
});

test('the policy names no CDN, and no pixel or font host while bio pages are off', function () {
    config(['bio.enabled' => false]);
    $csp = $this->get('/')->headers->get('Content-Security-Policy');
    foreach (array_merge(THIRD_PARTY, ['googletagmanager.com', 'google-analytics.com', 'connect.facebook.net', 'facebook.com']) as $host) {
        expect($csp)->not->toContain($host);
    }
});

test('a country without a flag file shows its code, never a picture from another host', function () {
    $html = country_flag('QQ');
    expect($html)->toContain('QQ')->not->toContain('<img')->not->toContain('flagcdn');
    expect(country_flag('ID'))->toContain('images/flags/id.svg');
});

test('the 500 page needs no CDN script', function () {
    $html = view('errors.500', ['exception' => new Exception('x')])->render();
    foreach (THIRD_PARTY as $host) {
        expect($html)->not->toContain($host);
    }
});

test('the preview and password pages of a link load nothing from another host', function () {
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'nothird', 'status' => 'active', 'target_url' => 'https://example.com/x', 'password_hash' => bcrypt('secret')]);
    $html = $this->get('/l/'.$link->slug)->assertOk()->getContent();
    foreach (THIRD_PARTY as $host) {
        expect($html)->not->toContain($host);
    }
});
