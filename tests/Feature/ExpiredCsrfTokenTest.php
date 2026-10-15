<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

// Laravel skips the CSRF check in tests; turn it on so a stale token behaves as in production.
beforeEach(function () {
    $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
});

test('logout with an expired csrf token still signs the user out instead of showing 419', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout', ['_token' => 'stale-token-from-an-old-tab']);

    $response->assertRedirect(route('login'))->assertSessionHas('status');
    $this->assertGuest();
});

test('another form with an expired token goes back with its input and a message', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->from('/links/create')->post('/links', [
        '_token' => 'stale',
        'target_url' => 'https://example.com',
        'password' => 'secret123',
    ]);

    $response->assertRedirect('/links/create')
        ->assertSessionHas('session_expired', 'Sesi habis, silakan coba lagi.')
        ->assertSessionHasInput('target_url', 'https://example.com')
        ->assertSessionMissing('_old_input.password');
    $this->assertAuthenticatedAs($user);
    $this->followRedirects($response)->assertSee('Sesi habis, silakan coba lagi.');
});

test('json requests with an expired token still get a 419 json response', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com'], ['X-CSRF-TOKEN' => 'stale'])
        ->assertStatus(419)->assertJson(['message' => 'CSRF token mismatch.']);
});

test('a fresh csrf token can be fetched for pages left open', function () {
    $this->getJson('/csrf-token')->assertOk()->assertJsonStructure(['token'])
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('the inactivity logout follows the session lifetime', function () {
    config(['session.lifetime' => 45]);
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(['last_activity_time' => now()->subMinutes(44)])->get('/dashboard')->assertOk();
    $this->actingAs($user)->withSession(['last_activity_time' => now()->subMinutes(46)])->get('/dashboard')->assertRedirect(route('login'));
});
