<?php

use App\Models\Link;
use App\Models\User;
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

    $this->get('/fresh1')->assertRedirect('https://example.com/fresh1');
    $this->get('/clicked')->assertRedirect('https://example.com/clicked');
    $this->get('/idle01')->assertNotFound();
    $this->get('/old001')->assertNotFound();
});

test('links owned by a user never expire this way', function () {
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'mine01', 'target_url' => 'https://example.com', 'status' => 'active'])
        ->forceFill(['created_at' => now()->subDays(400)])->save();

    $this->get('/mine01')->assertRedirect('https://example.com');
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

test('unverified users cannot create links or bio content', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->postJson('/api/bio/shorten', ['url' => 'https://example.com'])->assertForbidden();
    $this->actingAs($user)->post('/bio', ['slug' => 'abc123', 'title' => 'x'])->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->post('/links', ['target_url' => 'https://example.com', 'status' => 'active'])->assertRedirect(route('verification.notice'));
    // They can still sign in and see their dashboard.
    $this->actingAs($user)->get('/dashboard')->assertOk();
});

test('bot visits are not counted as clicks', function () {
    $link = guestLink('botcheck');
    Cache::flush();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')->get('/botcheck');
    expect($link->fresh()->clicks)->toBe(0)->and($link->fresh()->last_clicked_at)->toBeNull();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36')->get('/botcheck');
    expect($link->fresh()->clicks)->toBe(1);
});
