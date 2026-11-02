<?php

use App\Models\Link;
use App\Models\User;
use App\Services\QuotaService;
use Hop\Auth\Models\ApiToken;
use Illuminate\Support\Facades\Cache;

/*
| The account's link allowance is counted when a link is created, not remembered
| from an earlier request, and the check shares a transaction with the insert.
*/

function quotaLink(User $user, string $slug): Link
{
    return Link::create(['user_id' => $user->id, 'slug' => $slug, 'target_url' => 'https://example.com/'.$slug, 'status' => 'active']);
}

test('the shorten-web answer carries the title and the destination of the new link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => 'https://example.com/page', 'title' => 'My page'])
        ->assertCreated()
        ->assertJsonPath('title', 'My page')
        ->assertJsonPath('url', 'https://example.com/page')
        ->assertJsonStructure(['id', 'short_url']);
});

test('a full account is turned away on the next request, not after a cached answer expires', function () {
    config(['limits.user.max_active_links' => 2]);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => 'https://example.com/a'])->assertCreated();
    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => 'https://example.com/b'])->assertCreated();
    // the earlier "allowed" answers must not let a third one in
    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => 'https://example.com/c'])->assertStatus(429);
    expect(Link::where('user_id', $user->id)->count())->toBe(2)
        ->and(Cache::has("user-quota:{$user->id}"))->toBeFalse();

    // freeing a slot opens the door at once
    Link::where('user_id', $user->id)->first()->delete();
    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => 'https://example.com/d'])->assertCreated();
});

test('two creates that both passed the first check cannot both take the last slot', function () {
    config(['limits.user.max_active_links' => 1]);
    $user = User::factory()->create();
    $quotas = app(QuotaService::class);

    // what two concurrent requests see before either of them has inserted
    expect($quotas->checkUserLimits($user->id))->toBeTrue()->and($quotas->checkUserLimits($user->id))->toBeTrue();

    $first = $quotas->createWithinLimits($user->id, fn () => quotaLink($user, 'race01'));
    $second = $quotas->createWithinLimits($user->id, fn () => quotaLink($user, 'race02'));

    expect($first)->toBeInstanceOf(Link::class)->and($second)->toBeNull()
        ->and(Link::where('user_id', $user->id)->count())->toBe(1);
});

test('the daily allowance is held at the insert too, and one account does not use up another', function () {
    config(['limits.user.max_links_per_day' => 1]);
    $user = User::factory()->create();
    $other = User::factory()->create();
    $quotas = app(QuotaService::class);

    expect($quotas->createWithinLimits($user->id, fn () => quotaLink($user, 'day001')))->not->toBeNull()
        ->and($quotas->createWithinLimits($user->id, fn () => quotaLink($user, 'day002')))->toBeNull()
        ->and($quotas->createWithinLimits($other->id, fn () => quotaLink($other, 'day003')))->not->toBeNull();
});

test('a failing insert leaves nothing behind and the slot stays free', function () {
    config(['limits.user.max_active_links' => 1]);
    $user = User::factory()->create();
    $quotas = app(QuotaService::class);

    expect(fn () => $quotas->createWithinLimits($user->id, function () use ($user) {
        quotaLink($user, 'fail01');
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class);

    expect(Link::where('user_id', $user->id)->count())->toBe(0)
        ->and($quotas->createWithinLimits($user->id, fn () => quotaLink($user, 'fail02')))->not->toBeNull();
});

test('the token API stops at the limit even when the check before the insert passed', function () {
    config(['limits.user.max_active_links' => 1]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $token = ApiToken::generate($user->id, 'cli')['plain_token'];

    $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/shorten', ['url' => 'https://example.com/one'])->assertCreated();
    $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/shorten', ['url' => 'https://example.com/two'])->assertStatus(429);
    expect(Link::where('user_id', $user->id)->count())->toBe(1);
});
