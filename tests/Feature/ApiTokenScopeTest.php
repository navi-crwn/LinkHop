<?php

use App\Models\Link;
use App\Models\User;
use Hop\Auth\Models\ApiToken;

/*
| The token API: tokens come from the hub's shared api_tokens table (hop/auth
| validates them); scopes link:create, link:read, link:delete. A token with no
| scopes (older than scopes) may do everything.
*/
function bearer(User $user, ?array $scopes): array
{
    return ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, $scopes)['plain_token']];
}

test('POST /api/shorten needs link:create and answers with the public short URL', function () {
    config(['shortener.public_url' => 'https://hel.ink', 'app.url' => 'https://link.hel.ink']);
    $user = User::factory()->create();

    $this->withHeaders(bearer($user, ['bio:read']))->postJson('/api/shorten', ['url' => 'https://example.com'])->assertForbidden();
    $res = $this->withHeaders(bearer($user, ['link:create']))->postJson('/api/shorten', ['url' => 'https://example.com', 'alias' => 'mybio1'])->assertCreated();
    $res->assertJsonPath('success', true)->assertJsonPath('data.slug', 'mybio1')->assertJsonPath('data.url', 'https://hel.ink/l/mybio1');
    expect(Link::where('slug', 'mybio1')->value('user_id'))->toBe($user->id);
});

test('a token without scopes keeps working everywhere (made before scopes existed)', function () {
    $user = User::factory()->create();
    $this->withHeaders(bearer($user, null))->postJson('/api/shorten', ['url' => 'https://example.com'])->assertCreated();
});

test('GET and DELETE /api/links/{slug} need link:read and link:delete and only see the caller’s links', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'mine01', 'target_url' => 'https://example.com/m', 'status' => 'active']);
    Link::create(['user_id' => $other->id, 'slug' => 'theirs1', 'target_url' => 'https://example.com/t', 'status' => 'active']);

    $read = bearer($user, ['link:read']);
    $this->withHeaders($read)->getJson('/api/links/mine01')->assertOk()->assertJsonPath('data.target_url', 'https://example.com/m')->assertJsonPath('data.clicks', 0);
    $this->withHeaders($read)->getJson('/api/links/theirs1')->assertNotFound();
    $this->withHeaders($read)->deleteJson('/api/links/mine01')->assertForbidden();

    $this->withHeaders(bearer($user, ['link:delete']))->deleteJson('/api/links/theirs1')->assertNotFound();
    $this->withHeaders(bearer($user, ['link:delete']))->deleteJson('/api/links/mine01')->assertOk();
    expect(Link::where('slug', 'mine01')->exists())->toBeFalse()->and(Link::where('slug', 'theirs1')->exists())->toBeTrue();
});

test('an invalid, expired or missing token is refused with the usual JSON shape', function () {
    $user = User::factory()->create();
    $this->postJson('/api/shorten', ['url' => 'https://example.com'])->assertUnauthorized()->assertJsonPath('success', false);
    $this->withHeader('Authorization', 'Bearer hlk_nope')->postJson('/api/shorten', ['url' => 'https://example.com'])->assertUnauthorized();
    $expired = ApiToken::generate($user->id, 'old', 100, now()->subDay())['plain_token'];
    $this->withHeader('Authorization', 'Bearer '.$expired)->postJson('/api/shorten', ['url' => 'https://example.com'])->assertUnauthorized();
});

test('GET /api/me/links needs link:read and lists only the caller’s live links, with search and pages', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    foreach (range(1, 3) as $i) {
        Link::create(['user_id' => $user->id, 'slug' => "mine{$i}x", 'target_url' => "https://example.com/{$i}", 'custom_title' => "Mine {$i}", 'status' => 'active']);
    }
    Link::create(['user_id' => $user->id, 'slug' => 'offline1', 'target_url' => 'https://example.com/off', 'status' => 'disabled']);
    Link::create(['user_id' => $other->id, 'slug' => 'theirs9', 'target_url' => 'https://example.com/mine', 'custom_title' => 'Mine too', 'status' => 'active']);

    $this->withHeaders(bearer($user, ['link:create']))->getJson('/api/me/links')->assertForbidden();
    $this->flushHeaders()->getJson('/api/me/links')->assertUnauthorized();

    $read = bearer($user, ['link:read']);
    $all = $this->withHeaders($read)->getJson('/api/me/links?search=mine')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect(collect($all->json('data'))->pluck('slug')->all())->toBe(['mine3x', 'mine2x', 'mine1x'])
        ->and($all->json('next'))->toBe('');

    $first = $this->withHeaders($read)->getJson('/api/me/links?limit=2')->assertOk();
    expect(collect($first->json('data'))->pluck('slug')->all())->toBe(['mine3x', 'mine2x']);
    $second = $this->withHeaders($read)->getJson('/api/me/links?limit=2&cursor='.$first->json('next'))->assertOk();
    expect(collect($second->json('data'))->pluck('slug')->all())->toBe(['mine1x'])->and($second->json('next'))->toBe('');

    // LIKE wildcards are plain text.
    expect($this->withHeaders($read)->getJson('/api/me/links?search=%25')->json('data'))->toBe([]);
});
