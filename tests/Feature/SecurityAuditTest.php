<?php

use App\Models\ApiToken;
use App\Models\Link;
use App\Models\User;

test('catchphrase reset is throttled and constant across guesses', function () {
    $user = User::factory()->create(['catchphrase' => 'Swift River Owl']);
    foreach (range(1, 5) as $i) {
        $this->post('/forgot-password', ['email' => $user->email, 'catchphrase' => "wrong {$i}"])->assertSessionHasErrors('catchphrase');
    }
    // Even the right phrase is refused once the account is locked out.
    $this->post('/forgot-password', ['email' => $user->email, 'catchphrase' => 'Swift River Owl'])->assertSessionHasErrors('catchphrase');
    expect(session('password_reset_email'))->toBeNull();
});

test('catchphrase reset is refused for the superadmin', function () {
    config(['auth.superadmin_email' => 'root@example.com']);
    User::factory()->create(['email' => 'root@example.com', 'catchphrase' => 'Calm Star Fox']);

    $this->post('/forgot-password', ['email' => 'root@example.com', 'catchphrase' => 'calm star fox'])->assertSessionHasErrors('catchphrase');
    expect(session('password_reset_email'))->toBeNull();
});

test('the catchphrase reset session expires after ten minutes', function () {
    $user = User::factory()->create();
    $this->withSession(['password_reset_email' => $user->email, 'password_reset_at' => now()->subMinutes(11)->timestamp])
        ->post(route('password.reset.update'), ['password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertRedirect(route('password.request'));
});

test('api tokens of suspended users are rejected', function () {
    $user = User::factory()->create(['status' => User::STATUS_SUSPENDED]);
    $token = ApiToken::generate($user->id, 'cli')['plain_token'];

    $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/shorten', ['url' => 'https://example.com'])->assertForbidden();
});

test('disabling a link takes effect immediately despite the redirect cache', function () {
    $link = Link::create(['slug' => 'cached1', 'target_url' => 'https://example.com', 'status' => 'active']);
    $this->get('/cached1')->assertRedirect('https://example.com'); // warms the cache

    $link->update(['status' => Link::STATUS_INACTIVE]);
    $this->get('/cached1')->assertNotFound();
});

test('links of suspended users stop resolving', function () {
    $user = User::factory()->create(['status' => User::STATUS_SUSPENDED]);
    Link::create(['user_id' => $user->id, 'slug' => 'susp01', 'target_url' => 'https://example.com', 'status' => 'active']);

    $this->get('/susp01')->assertNotFound();
});

test('csv cells that look like formulas are neutralised', function () {
    expect(csv_safe(['=HYPERLINK("x")', '+1', 'plain', 5]))->toBe(["'=HYPERLINK(\"x\")", "'+1", 'plain', 5]);
});
