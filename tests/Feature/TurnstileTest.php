<?php

use App\Models\User;
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

test('login, register and password reset require a valid token once configured', function () {
    config(['services.turnstile.secret' => 'test-secret', 'services.turnstile.site_key' => '1x00000000000000000000AA']);
    Http::fake([SITEVERIFY => fn (Request $r) => Http::response(['success' => $r['response'] === 'good'])]);
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'cf-turnstile-response' => 'bad'])->assertSessionHasErrors('turnstile');
    $this->assertGuest();
    $this->post('/forgot-password', ['email' => $user->email, 'cf-turnstile-response' => 'bad'])->assertSessionHasErrors('turnstile');

    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'cf-turnstile-response' => 'good']);
    $this->assertAuthenticated();
});

test('auth pages render the turnstile widget container when configured', function () {
    config(['services.turnstile.site_key' => '1x00000000000000000000AA']);

    foreach (['/login', '/register', '/forgot-password', '/report-abuse'] as $path) {
        $this->get($path)->assertOk()
            ->assertSee('turnstileForm(', false)
            ->assertSee('id="turnstile-script"', false)
            ->assertSee('name="cf-turnstile-response"', false);
    }
});

test('the queue health endpoint is reachable (not swallowed by the short-link catch-all)', function () {
    $this->getJson('/health/queue')->assertOk()->assertJsonStructure(['status', 'last_heartbeat']);
});
