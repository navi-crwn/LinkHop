<?php

use App\Models\Link;
use App\Models\RetiredSlug;
use App\Models\User;
use Carbon\CarbonImmutable;
use Hop\Auth\Models\ApiToken;
use Illuminate\Support\Facades\Cache;

function guestLink(string $slug, array $attrs = []): Link
{
    $link = Link::create(['slug' => $slug, 'target_url' => 'https://example.com/'.$slug, 'status' => 'active']);
    $link->forceFill($attrs)->save();

    return $link;
}

test('guest links stop redirecting after 14 idle days or 30 days total', function () {
    guestLink('fresh1');
    guestLink('clicked', ['created_at' => now()->subDays(20), 'last_clicked_at' => now()->subDays(2)]);
    guestLink('idle01', ['created_at' => now()->subDays(15)]);
    guestLink('old001', ['created_at' => now()->subDays(31), 'last_clicked_at' => now()->subDay()]);
    Cache::flush();

    $this->get('/l/fresh1')->assertRedirect('https://example.com/fresh1');
    $this->get('/l/clicked')->assertRedirect('https://example.com/clicked');
    $this->get('/l/idle01')->assertStatus(410);
    $this->get('/l/old001')->assertStatus(410);
});

test('a guest link lives through day 30 and is gone at 00:00 after it, even if clicked on day 29', function () {
    config(['shortener.guest_timezone' => 'Asia/Jakarta']);
    $this->travelTo(CarbonImmutable::parse('2026-10-01 15:00', 'Asia/Jakarta'));
    $link = guestLink('day30');

    // clicked on day 29 (29 Oct), so the idle rule is not what removes it
    $this->travelTo(CarbonImmutable::parse('2026-10-29 20:00', 'Asia/Jakarta'));
    $link->forceFill(['last_clicked_at' => now()])->save();

    $this->travelTo(CarbonImmutable::parse('2026-10-30 23:59:59', 'Asia/Jakarta'));
    expect($link->fresh()->isGuestExpired())->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-10-31 00:00', 'Asia/Jakarta'));
    expect($link->fresh()->isGuestExpired())->toBeTrue();
    $this->artisan('links:prune-guest')->assertSuccessful();
    expect(Link::find($link->id))->toBeNull();
});

test('a guest link is gone at 00:00 after 14 full days in a row without a click', function () {
    config(['shortener.guest_timezone' => 'Asia/Jakarta']);
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00', 'Asia/Jakarta'));
    $link = guestLink('idle14');

    // last click on 5 Oct; 6..19 Oct are the 14 days without a click
    $this->travelTo(CarbonImmutable::parse('2026-10-05 22:00', 'Asia/Jakarta'));
    $link->forceFill(['last_clicked_at' => now()])->save();

    $this->travelTo(CarbonImmutable::parse('2026-10-19 23:59:59', 'Asia/Jakarta'));
    expect($link->fresh()->isGuestExpired())->toBeFalse()
        ->and(Link::guestExpired()->whereKey($link->id)->exists())->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-10-20 00:00', 'Asia/Jakarta'));
    expect($link->fresh()->isGuestExpired())->toBeTrue()
        ->and(Link::guestExpired()->whereKey($link->id)->exists())->toBeTrue();
});

test('a deleted guest link shows an expired page and its slug stays blocked for a while', function () {
    $old = guestLink('promo1', ['created_at' => now()->subDays(40)]);
    Cache::flush();
    $this->get('/l/promo1')->assertStatus(410)->assertSee('Link Expired');

    $this->artisan('links:prune-guest')->assertSuccessful();
    expect(Link::find($old->id))->toBeNull()
        ->and(RetiredSlug::isBlocked('PROMO1'))->toBeTrue();
    Cache::flush();
    $this->get('/l/promo1')->assertStatus(410);

    // nobody can take the slug over and catch its old visitors
    $user = User::factory()->create();
    $this->actingAs($user)->post('/links', ['target_url' => 'https://evil.example', 'slug' => 'promo1', 'status' => 'active'])
        ->assertSessionHasErrors('slug');
    expect(Link::where('slug', 'promo1')->exists())->toBeFalse();

    // once the block has passed, the slug is free again and the record is cleaned up
    $this->travel((int) config('shortener.retired_slug_days') + 1)->days();
    $this->artisan('links:prune-guest')->assertSuccessful();
    expect(RetiredSlug::where('slug', 'promo1')->exists())->toBeFalse();
});

test('links owned by a user never expire this way', function () {
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'mine01', 'target_url' => 'https://example.com', 'status' => 'active'])
        ->forceFill(['created_at' => now()->subDays(400)])->save();

    $this->get('/l/mine01')->assertRedirect('https://example.com');
});

test('prune command deletes only expired guest links', function () {
    $keep = guestLink('keep01');
    $gone = guestLink('gone01', ['created_at' => now()->subDays(15)]);

    $this->artisan('links:prune-guest', ['--dry-run' => true])->assertSuccessful();
    expect(Link::find($gone->id))->not->toBeNull();

    $this->artisan('links:prune-guest')->assertSuccessful();
    expect(Link::find($gone->id))->toBeNull()
        ->and(Link::find($keep->id))->not->toBeNull();
});

test('the token API refuses an unverified account; the hub never signs one in here', function () {
    $user = User::factory()->unverified()->create();
    $token = ApiToken::generate($user->id, 'cli')['plain_token'];

    $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/shorten', ['url' => 'https://example.com'])->assertForbidden();
    // Verified at the hub by Google: allowed.
    $user->forceFill(['google_id' => 'g-1'])->save();
    $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/shorten', ['url' => 'https://example.com'])->assertCreated();
});

test('bot visits are not counted as clicks', function () {
    $link = guestLink('botcheck');
    Cache::flush();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')->get('/l/botcheck');
    expect($link->fresh()->clicks)->toBe(0)->and($link->fresh()->last_clicked_at)->toBeNull();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36')->get('/l/botcheck');
    expect($link->fresh()->clicks)->toBe(1);
});
