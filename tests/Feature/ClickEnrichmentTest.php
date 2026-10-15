<?php

use App\Jobs\LogLinkClick;
use App\Models\Link;
use App\Services\GeoIpService;
use App\Services\ProxyDetection\Detectors\VpnApiDetector;
use App\Services\ProxyDetectionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

test('vpnapi answers without a vpn key do not throw', function () {
    Http::fake(['vpnapi.io/*' => Http::response(['message' => 'quota exceeded', 'security' => ['proxy' => true]])]);

    $result = (new VpnApiDetector)->detect('203.0.113.9');

    expect($result['is_proxy'])->toBeTrue();
});

test('a failing proxy lookup still counts the click', function () {
    Http::fake(['*' => Http::response(['security' => []])]);
    $this->mock(ProxyDetectionService::class)
        ->shouldReceive('detect')->andThrow(new RuntimeException('lookup down'));
    $link = Link::create(['slug' => 'enrich', 'target_url' => 'https://example.com/enrich', 'status' => 'active']);

    (new LogLinkClick($link->id, '203.0.113.9', null, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'))
        ->handle(app(GeoIpService::class));

    expect($link->fresh()->clicks)->toBe(1);
});

test('no GET route is shadowed by a directory in public/', function () {
    $shadowed = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array('GET', $r->methods()) && ! str_contains($r->uri(), '{'))
        ->map(fn ($r) => $r->uri())
        ->filter(fn ($uri) => $uri !== '/' && is_dir(public_path($uri)))
        ->values()->all();

    expect($shadowed)->toBe([]);
});
