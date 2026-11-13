<?php

use App\Models\Link;
use App\Models\User;
use Hop\Auth\Models\ApiToken;
use Illuminate\Support\Facades\Hash;

/*
| The token API: tokens come from the hub's shared api_tokens table (hop/auth
| validates them); scopes link:create, link:read, and link:write to delete (link:delete is
| accepted too). A token with no scopes (older than scopes) may do everything.
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

test('GET /api/links/{slug} needs link:read and only sees the caller’s links', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'mine01', 'target_url' => 'https://example.com/m', 'status' => 'active']);
    Link::create(['user_id' => $other->id, 'slug' => 'theirs1', 'target_url' => 'https://example.com/t', 'status' => 'active']);

    $read = bearer($user, ['link:read']);
    $this->withHeaders($read)->getJson('/api/links/mine01')->assertOk()->assertJsonPath('data.target_url', 'https://example.com/m')->assertJsonPath('data.clicks', 0);
    $this->withHeaders($read)->getJson('/api/links/theirs1')->assertNotFound();
    $this->withHeaders(bearer($user, ['link:create']))->getJson('/api/links/mine01')->assertForbidden();
});

test('DELETE /api/links/{slug} takes link:write, the scope the hub offers, as well as link:delete, link:* and an unscoped token', function () {
    // The hub's token form offers link:write as "Edit and delete short links" (hop/auth ApiToken::SCOPES).
    expect(ApiToken::SCOPES)->toHaveKey('link:write');
    $user = User::factory()->create();
    $other = User::factory()->create();
    Link::create(['user_id' => $other->id, 'slug' => 'theirs1', 'target_url' => 'https://example.com/t', 'status' => 'active']);

    foreach (['write' => ['link:write'], 'delete' => ['link:delete'], 'star' => ['link:*'], 'all' => null] as $name => $scopes) {
        Link::create(['user_id' => $user->id, 'slug' => "del-{$name}", 'target_url' => 'https://example.com/'.$name, 'status' => 'active']);
        $token = bearer($user, $scopes);
        $this->withHeaders($token)->deleteJson('/api/links/theirs1')->assertNotFound();
        $this->withHeaders($token)->deleteJson("/api/links/del-{$name}")->assertOk()->assertJsonPath('success', true);
        expect(Link::where('slug', "del-{$name}")->exists())->toBeFalse($name);
    }
    expect(Link::where('slug', 'theirs1')->exists())->toBeTrue();
});

test('DELETE /api/links/{slug} refuses link:read and the other scopes with the usual JSON', function () {
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'mine01', 'target_url' => 'https://example.com/m', 'status' => 'active']);

    foreach ([['link:read'], ['link:create'], ['bio:write', 'bio:*']] as $scopes) {
        $this->withHeaders(bearer($user, $scopes))->deleteJson('/api/links/mine01')
            ->assertForbidden()->assertJsonPath('success', false)->assertJsonPath('error', 'Forbidden');
    }
    $this->flushHeaders()->deleteJson('/api/links/mine01')->assertUnauthorized();
    expect(Link::where('slug', 'mine01')->exists())->toBeTrue();
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

/*
| The API alias rule follows the link form: letters, numbers and hyphens, 3 to 48. The API
| used to take "_" and up to 255, so my_link could be made but never read or deleted through
| the API routes. Old links with an underscore keep opening and can still be read and deleted;
| a new alias follows the form rule. The link password is at least 6 letters here as on the form.
*/

test('POST /api/shorten follows the link form’s alias rule: 3 to 48 letters, numbers and hyphens', function (string $alias) {
    $user = User::factory()->create();
    $this->withHeaders(bearer($user, ['link:create']))->postJson('/api/shorten', ['url' => 'https://example.com', 'alias' => $alias])
        ->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('error', 'Validation Error')
        ->assertJsonStructure(['messages' => ['alias']]);
    expect(Link::where('slug', $alias)->exists())->toBeFalse($alias);
})->with(['a_b', 'ab', str_repeat('x', 49), 'my link', 'my.link', 'ünï']);

test('POST /api/shorten still makes a link for a good alias and for no alias at all', function () {
    $user = User::factory()->create();
    $token = bearer($user, ['link:create']);
    $long = str_repeat('x', 48);
    $this->withHeaders($token)->postJson('/api/shorten', ['url' => 'https://example.com', 'alias' => 'my-link'])->assertCreated()->assertJsonPath('data.slug', 'my-link');
    $this->withHeaders($token)->postJson('/api/shorten', ['url' => 'https://example.com', 'alias' => $long])->assertCreated()->assertJsonPath('data.slug', $long);
    $res = $this->withHeaders($token)->postJson('/api/shorten', ['url' => 'https://example.com'])->assertCreated();
    expect($res->json('data.slug'))->not->toBe('');
});

test('a link that already has an underscore slug still opens and can be read and deleted through the API', function () {
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'my_link', 'target_url' => 'https://example.com/old', 'status' => 'active']);

    $this->get('/l/my_link')->assertRedirect('https://example.com/old');
    $this->withHeaders(bearer($user, ['link:read']))->getJson('/api/links/my_link')->assertOk()->assertJsonPath('data.slug', 'my_link')->assertJsonPath('data.target_url', 'https://example.com/old');
    $this->withHeaders(bearer($user, ['link:write']))->deleteJson('/api/links/my_link')->assertOk()->assertJsonPath('success', true);
    expect(Link::where('slug', 'my_link')->exists())->toBeFalse();
});

test('POST /api/shorten wants a link password of at least six letters, as the link form does', function () {
    $user = User::factory()->create();
    $token = bearer($user, ['link:create']);
    $this->withHeaders($token)->postJson('/api/shorten', ['url' => 'https://example.com/pw5', 'password' => 'abcde'])
        ->assertStatus(422)->assertJsonPath('error', 'Validation Error')->assertJsonStructure(['messages' => ['password']]);
    $res = $this->withHeaders($token)->postJson('/api/shorten', ['url' => 'https://example.com/pw6', 'password' => 'abcdef'])->assertCreated();
    $hash = Link::where('slug', $res->json('data.slug'))->value('password_hash');
    expect($hash)->not->toBeNull()->and(Hash::check('abcdef', $hash))->toBeTrue();
});
