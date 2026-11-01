<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use Hop\Auth\Models\ApiToken;
use Illuminate\Support\Facades\Cache;

function coreLink(array $attrs = []): Link
{
    static $n = 0;
    $n++;
    $user = $attrs['__user'] ?? User::factory()->create();
    unset($attrs['__user']);

    return Link::create($attrs + ['user_id' => $user->id, 'slug' => 'core'.$n, 'target_url' => 'https://example.com/core'.$n, 'status' => 'active']);
}

const HUMAN = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
const BOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

test('a link with a start time says it is not live yet, and works once the time comes', function () {
    $link = coreLink(['starts_at' => now()->addHour()]);
    $this->get('/l/'.$link->slug)->assertStatus(404)->assertSee('Not live yet')->assertHeader('Cache-Control', 'no-store, private');
    expect(LinkClick::count())->toBe(0);
    $this->travel(2)->hours();
    Cache::flush();
    $this->get('/l/'.$link->slug)->assertRedirect($link->target_url);
});

test('an expired link goes to its fallback address, or says so when there is none', function () {
    $with = coreLink(['expires_at' => now()->subMinute(), 'fallback_url' => 'https://example.com/sorry']);
    $without = coreLink(['expires_at' => now()->subMinute()]);
    $this->get('/l/'.$with->slug)->assertRedirect('https://example.com/sorry')->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/l/'.$without->slug)->assertStatus(410)->assertSee('This link has ended');
});

test('a click limit lets exactly that many people through, whatever the cache says', function () {
    $link = coreLink(['max_clicks' => 2]);
    $this->withHeader('User-Agent', HUMAN)->get('/l/'.$link->slug)->assertRedirect($link->target_url);
    $this->withHeader('User-Agent', HUMAN)->get('/l/'.$link->slug)->assertRedirect($link->target_url);
    expect($link->fresh()->clicks)->toBe(2); // counted once each, not twice
    $this->withHeader('User-Agent', HUMAN)->get('/l/'.$link->slug)->assertStatus(410)->assertSee('all of them have been used');
    expect($link->fresh()->clicks)->toBe(2)->and(LinkClick::where('link_id', $link->id)->count())->toBe(2);
});

test('after the limit the visitor goes to the fallback address', function () {
    $link = coreLink(['max_clicks' => 1, 'fallback_url' => 'https://example.com/all-gone']);
    $this->withHeader('User-Agent', HUMAN)->get('/l/'.$link->slug)->assertRedirect($link->target_url);
    $this->withHeader('User-Agent', HUMAN)->get('/l/'.$link->slug)->assertRedirect('https://example.com/all-gone');
});

test('crawlers and link-preview bots do not use up the limit', function () {
    $link = coreLink(['max_clicks' => 1]);
    $this->withHeader('User-Agent', BOT)->get('/l/'.$link->slug)->assertRedirect($link->target_url);
    expect($link->fresh()->clicks)->toBe(0);
    $this->withHeader('User-Agent', HUMAN)->get('/l/'.$link->slug)->assertRedirect($link->target_url);
    expect($link->fresh()->clicks)->toBe(1);
});

test('a password page does not use a click until the visitor is let through', function () {
    $link = coreLink(['max_clicks' => 1, 'password_hash' => bcrypt('secret1')]);
    $this->withHeader('User-Agent', HUMAN)->get('/l/'.$link->slug)->assertOk();
    expect($link->fresh()->clicks)->toBe(0);
    $this->withHeader('User-Agent', HUMAN)->post('/l/'.$link->slug, ['password' => 'secret1'])->assertRedirect($link->target_url);
    expect($link->fresh()->clicks)->toBe(1);
});

test('a link without a limit counts like before', function () {
    $link = coreLink();
    $this->withHeader('User-Agent', HUMAN)->get('/l/'.$link->slug)->assertRedirect($link->target_url);
    expect($link->fresh()->clicks)->toBe(1);
});

test('live and ended scopes know about the start and the limit', function () {
    $live = coreLink();
    $later = coreLink(['starts_at' => now()->addDay()]);
    $full = coreLink(['max_clicks' => 3, 'clicks' => 3]);
    $open = coreLink(['max_clicks' => 3, 'clicks' => 2]);
    expect(Link::live()->pluck('id')->all())->toEqualCanonicalizing([$live->id, $open->id]);
    expect(Link::ended()->pluck('id')->all())->toBe([$full->id]);
    expect($later->isNotStarted())->toBeTrue()->and($full->isLimitReached())->toBeTrue()->and($full->hasEnded())->toBeTrue();
});

test('the form takes the limits, 308, and refuses bad ones', function () {
    $user = User::factory()->create();
    $base = ['target_url' => 'https://example.com/limits', 'status' => 'active'];
    $ok = $this->actingAs($user)->postJson('/links', $base + ['max_clicks' => 50, 'starts_at' => now()->addDay()->toDateTimeString(), 'expires_at' => now()->addDays(5)->toDateTimeString(), 'fallback_url' => 'https://example.com/later', 'redirect_type' => '308', 'slug' => 'limits1'])->assertOk();
    $link = Link::where('slug', 'limits1')->first();
    expect($link->max_clicks)->toBe(50)->and($link->fallback_url)->toBe('https://example.com/later')->and($link->redirect_type)->toBe('308')->and($link->starts_at)->not->toBeNull();

    $this->actingAs($user)->postJson('/links', $base + ['target_url' => 'https://example.com/b1', 'max_clicks' => 0])->assertStatus(422)->assertJsonValidationErrors('max_clicks');
    $this->actingAs($user)->postJson('/links', $base + ['target_url' => 'https://example.com/b2', 'max_clicks' => 'many'])->assertStatus(422)->assertJsonValidationErrors('max_clicks');
    $this->actingAs($user)->postJson('/links', $base + ['target_url' => 'https://example.com/b3', 'starts_at' => now()->addDays(3)->toDateTimeString(), 'expires_at' => now()->addDays(2)->toDateTimeString()])->assertStatus(422)->assertJsonValidationErrors('expires_at');
    $this->actingAs($user)->postJson('/links', $base + ['target_url' => 'https://example.com/b4', 'fallback_url' => 'not a url'])->assertStatus(422)->assertJsonValidationErrors('fallback_url');
});

test('a fallback address cannot be another short link, which could loop', function () {
    config(['shortener.public_url' => 'https://hel.ink']);
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/loop', 'status' => 'active', 'fallback_url' => 'https://hel.ink/l/anything'])->assertStatus(422)->assertJsonValidationErrors('fallback_url');
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/loop2', 'status' => 'active', 'fallback_url' => 'https://HEL.ink/L/Anything'])->assertStatus(422)->assertJsonValidationErrors('fallback_url');
});

test('the same address twice is flagged once, then allowed when the form is sent again', function () {
    $user = User::factory()->create();
    $payload = ['target_url' => 'https://Example.com/same/', 'status' => 'active', 'slug' => 'dupe-a'];
    $this->actingAs($user)->postJson('/links', $payload)->assertOk();

    $second = ['target_url' => 'https://example.com/same', 'status' => 'active', 'slug' => 'dupe-b'];
    $this->actingAs($user)->postJson('/links', $second)->assertStatus(409)->assertJsonPath('duplicate.slug', 'dupe-a');
    expect(Link::where('slug', 'dupe-b')->exists())->toBeFalse();
    $this->actingAs($user)->postJson('/links', $second)->assertOk();
    expect(Link::where('slug', 'dupe-b')->exists())->toBeTrue();
});

test('another account is never warned about someone else having the same address', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $this->actingAs($a)->postJson('/links', ['target_url' => 'https://example.com/shared', 'status' => 'active'])->assertOk();
    $this->actingAs($b)->postJson('/links', ['target_url' => 'https://example.com/shared', 'status' => 'active'])->assertOk();
});

test('a different address in between resets nothing it should not: a new duplicate is warned again', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/x', 'status' => 'active'])->assertOk();
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/x', 'status' => 'active'])->assertStatus(409);
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/y', 'status' => 'active'])->assertOk();
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/y', 'status' => 'active'])->assertStatus(409);
});

test('the API takes and returns the limits', function () {
    $user = User::factory()->create();
    $h = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create'])['plain_token']];
    $res = $this->withHeaders($h)->postJson('/api/shorten', ['url' => 'https://example.com/api', 'alias' => 'apicore', 'max_clicks' => 10, 'fallback_url' => 'https://example.com/over', 'redirect_type' => '308', 'starts_at' => now()->addHour()->toIso8601String()])->assertCreated();
    $res->assertJsonPath('data.max_clicks', 10)->assertJsonPath('data.fallback_url', 'https://example.com/over')->assertJsonPath('data.redirect_type', '308');
    expect($res->json('data.starts_at'))->not->toBeNull();
    $this->withHeaders($h)->postJson('/api/shorten', ['url' => 'https://example.com/api2', 'max_clicks' => -1])->assertStatus(422);
});

test('the edit page shows the limits and saves them', function () {
    $user = User::factory()->create();
    $link = coreLink(['__user' => $user, 'slug' => 'editcore']);
    $this->actingAs($user)->get('/links/'.$link->id.'/edit')->assertOk()->assertSee('Limits and schedule')->assertSee('name="max_clicks"', false)->assertSee('308');
    $this->actingAs($user)->put('/links/'.$link->id, ['target_url' => $link->target_url, 'status' => 'active', 'slug' => 'editcore', 'max_clicks' => 5, 'fallback_url' => 'https://example.com/end'])->assertRedirect();
    expect($link->fresh()->max_clicks)->toBe(5)->and($link->fresh()->fallback_url)->toBe('https://example.com/end');
});

test('the list shows where a link stands against its limit and start', function () {
    $user = User::factory()->create();
    coreLink(['__user' => $user, 'slug' => 'limitchip', 'max_clicks' => 10, 'clicks' => 4]);
    coreLink(['__user' => $user, 'slug' => 'fullchip', 'max_clicks' => 3, 'clicks' => 3]);
    coreLink(['__user' => $user, 'slug' => 'soonchip', 'starts_at' => now()->addDays(2)]);
    $this->actingAs($user)->get('/links')->assertOk()->assertSee('4/10')->assertSee('Limit reached')->assertSee('Starts in');
});
