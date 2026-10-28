<?php

use App\Models\Link;
use App\Services\TurnstileService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const SITEVERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

test('a valid token passes and the real client ip and secret are sent', function () {
    config(['services.turnstile.secret' => 'test-secret']);
    Http::fake([SITEVERIFY => Http::response(['success' => true])]);

    expect(app(TurnstileService::class)->verify('good-token', '203.0.113.9'))->toBeTrue();
    Http::assertSent(fn (Request $r) => $r['secret'] === 'test-secret' && $r['response'] === 'good-token' && $r['remoteip'] === '203.0.113.9');
});

test('a rejected token, a missing token or a network error fail', function () {
    config(['services.turnstile.secret' => 'test-secret']);
    Http::fake([SITEVERIFY => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
    $turnstile = app(TurnstileService::class);

    expect($turnstile->verify('bad-token'))->toBeFalse()
        ->and($turnstile->verify(''))->toBeFalse()
        ->and($turnstile->verify(null))->toBeFalse();

    Http::fake([SITEVERIFY => fn () => throw new ConnectionException('down')]);
    expect($turnstile->verify('any'))->toBeFalse();
});

test('without a secret it fails open while developing and closed in production', function () {
    config(['services.turnstile.secret' => '']);
    $turnstile = app(TurnstileService::class);
    expect($turnstile->verify(null))->toBeTrue();

    app()->detectEnvironment(fn () => 'production');
    expect($turnstile->verify('anything'))->toBeFalse();
    app()->detectEnvironment(fn () => 'testing');
});

test('the guest shortener requires a valid token once configured', function () {
    config(['services.turnstile.secret' => 'test-secret', 'services.turnstile.site_key' => '1x00000000000000000000AA']);
    Http::fake([SITEVERIFY => fn (Request $r) => Http::response(['success' => $r['response'] === 'good'])]);

    $this->post('/shorten', ['target_url' => 'https://example.com/a', 'cf-turnstile-response' => 'bad'])->assertSessionHasErrors('turnstile');
    expect(Link::where('target_url', 'https://example.com/a')->exists())->toBeFalse();

    $this->post('/shorten', ['target_url' => 'https://example.com/a', 'cf-turnstile-response' => 'good'])
        ->assertRedirect(route('home'))
        ->assertSessionHas('shortened_link');
    $link = Link::where('target_url', 'https://example.com/a')->sole();
    expect($link->user_id)->toBeNull()
        ->and($link->short_url)->toContain('/l/'.$link->slug);
});

test('the landing and the abuse form render the turnstile widget container when configured', function () {
    config(['services.turnstile.site_key' => '1x00000000000000000000AA']);

    foreach (['/', '/report-abuse'] as $path) {
        $this->get($path)->assertOk()
            ->assertSee('turnstileForm(', false)
            ->assertSee('id="turnstile-script"', false)
            ->assertSee('name="cf-turnstile-response"', false);
    }
});

test('the queue health endpoint is reachable (not swallowed by the short-link catch-all)', function () {
    $this->getJson('/health/queue')->assertOk()->assertJsonStructure(['status', 'last_heartbeat']);
});
