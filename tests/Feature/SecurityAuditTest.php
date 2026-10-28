<?php

use App\Models\ApiToken;
use App\Models\Link;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

test('api tokens of suspended users are rejected', function () {
    $user = User::factory()->create(['status' => User::STATUS_SUSPENDED]);
    $token = ApiToken::generate($user->id, 'cli')['plain_token'];

    $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/shorten', ['url' => 'https://example.com'])->assertForbidden();
});

test('disabling a link takes effect immediately despite the redirect cache', function () {
    $link = Link::create(['slug' => 'cached1', 'target_url' => 'https://example.com', 'status' => 'active']);
    $this->get('/l/cached1')->assertRedirect('https://example.com'); // warms the cache

    $link->update(['status' => Link::STATUS_INACTIVE]);
    $this->get('/l/cached1')->assertNotFound();
});

test('links of suspended users stop resolving', function () {
    $user = User::factory()->create(['status' => User::STATUS_SUSPENDED]);
    Link::create(['user_id' => $user->id, 'slug' => 'susp01', 'target_url' => 'https://example.com', 'status' => 'active']);

    $this->get('/l/susp01')->assertNotFound();
});

test('csv cells that look like formulas are neutralised', function () {
    expect(csv_safe(['=HYPERLINK("x")', '+1', 'plain', 5]))->toBe(["'=HYPERLINK(\"x\")", "'+1", 'plain', 5]);
});

test('client ip comes from cloudflare forwarded headers only when cloudflare proxies are trusted', function () {
    config(['app.trusted_proxies' => 'cloudflare']);
    (new AppServiceProvider(app()))->boot();
    // A path the /{slug} catch-all and the /admin/* control-center route both leave alone.
    Route::get('/email/__ip', fn (Request $r) => $r->ip());

    $this->withServerVariables(['REMOTE_ADDR' => '172.70.1.1'])
        ->withHeader('X-Forwarded-For', '203.0.113.9')
        ->get('/email/__ip')->assertSee('203.0.113.9');
    // A spoofed header from a non-Cloudflare address is ignored.
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.4'])
        ->withHeader('X-Forwarded-For', '203.0.113.9')
        ->get('/email/__ip')->assertSee('198.51.100.4');

    TrustProxies::flushState();
});

test('pages carry a per-request script nonce and accept csp reports', function () {
    $response = $this->get('/l/login');
    preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy-Report-Only'), $m);

    expect($m[1] ?? null)->not->toBeNull();
    $response->assertSee('nonce="'.$m[1].'"', false);
    $this->postJson('/csp-report', ['csp-report' => ['violated-directive' => 'script-src-attr']])->assertNoContent();
});
