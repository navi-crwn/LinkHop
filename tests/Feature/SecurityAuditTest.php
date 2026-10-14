<?php

use App\Models\ApiToken;
use App\Models\Link;
use App\Models\User;

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
