<?php

use App\Models\User;
use App\Support\FamilyAnnouncements;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
| One announcement for the whole family, written once on the hub and read here: GET {hub}/announcements.json?app=linkhop.
| The dashboard shows at most one card; a hub that is down, slow or missing must never cost the member a page.
*/

const HUB = 'https://hub.example.test';

function announcement(array $over = []): array
{
    return array_merge([
        'id' => '12', 'level' => 'info', 'title' => 'Scheduled maintenance', 'body' => 'HOP will be read-only for ten minutes.',
        'url' => 'https://hel.ink/announcements/12', 'published_at' => now()->subHour()->toIso8601ZuluString(), 'expires_at' => null, 'apps' => ['*'],
    ], $over);
}

/** Each call starts a new pretend hub: the first matching answer of a Http::fake would otherwise win for good. */
function hubSaysRaw(mixed $answer): void
{
    config(['hop.family_announcements' => true, 'hop.hub_url' => HUB]);
    Http::swap(new Factory);
    Http::fake([HUB.'/*' => $answer]);
}

function hubSays(array $items): void
{
    hubSaysRaw(Http::response(['version' => 1, 'generated_at' => now()->toIso8601ZuluString(), 'items' => $items]));
}

/** A hub that cannot be reached; the returned counter says how often it was asked. */
function hubDown(): object
{
    $asked = new stdClass;
    $asked->count = 0;
    config(['hop.family_announcements' => true, 'hop.hub_url' => HUB]);
    Http::swap(new Factory);
    Http::fake(function () use ($asked) {
        $asked->count++;
        throw new ConnectionException('hub is down');
    });

    return $asked;
}

function member(): User
{
    return User::factory()->create();
}

it('shows a live announcement on the dashboard, asking the hub for this app only', function () {
    hubSays([announcement()]);

    $this->actingAs(member())->get('/dashboard')->assertOk()
        ->assertSee('Scheduled maintenance')->assertSee('HOP will be read-only for ten minutes.')
        ->assertSee('href="https://hel.ink/announcements/12"', false)->assertSee('data-hop-remind="ann-12"', false);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), HUB.'/announcements.json') && $request['app'] === 'linkhop');
});

it('shows it on the dashboard only', function () {
    hubSays([announcement()]);

    $this->actingAs(member())->get('/links')->assertOk()->assertDontSee('Scheduled maintenance');
});

it('shows nothing, and does not fail, when the hub is down, and does not ask again for a minute', function () {
    $asked = hubDown();
    $user = member();

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('data-hop-remind="ann-');
    $this->actingAs($user)->get('/dashboard')->assertOk();
    expect($asked->count)->toBe(1);
});

it('shows nothing when the hub answers with an error or with something that is not the list', function () {
    foreach ([Http::response('boom', 500), Http::response('not json', 200), Http::response(['items' => 'nope'], 200), Http::response(['version' => 1, 'items' => [['id' => 5]]], 200)] as $answer) {
        Cache::flush();
        hubSaysRaw($answer);
        $this->actingAs(member())->get('/dashboard')->assertOk()->assertDontSee('data-hop-remind="ann-');
    }
});

it('keeps showing the last good copy when the hub goes away later', function () {
    hubSays([announcement()]);
    $user = member();
    $this->actingAs($user)->get('/dashboard')->assertSee('Scheduled maintenance');

    Cache::forget(FamilyAnnouncements::FRESH_KEY);
    hubDown();
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Scheduled maintenance');
});

it('asks nothing of anyone without a hub address', function () {
    config(['hop.family_announcements' => true, 'hop.hub_url' => '']);
    Http::fake();

    $this->actingAs(member())->get('/dashboard')->assertOk()->assertDontSee('data-hop-remind="ann-');
    Http::assertNothingSent();
});

it('prints the text as text, never as HTML, and keeps unsafe links off the title', function () {
    hubSays([announcement(['title' => '<b>Big</b> news', 'body' => '<script>alert(1)</script> plain', 'url' => 'javascript:alert(1)'])]);

    $page = $this->actingAs(member())->get('/dashboard')->assertOk();
    $page->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; plain', false)
        ->assertSee('&lt;b&gt;Big&lt;/b&gt; news', false)->assertDontSee('javascript:alert', false);
});

it('cuts a long text to a short card', function () {
    hubSays([announcement(['body' => str_repeat('word ', 200)])]);

    $html = $this->actingAs(member())->get('/dashboard')->assertOk()->getContent();
    expect($html)->not->toContain(str_repeat('word ', 80));
});

it('shows one card: critical first, then the newest, and skips what is over or meant for another app', function () {
    hubSays([
        announcement(['id' => '1', 'title' => 'Old news', 'published_at' => now()->subDays(3)->toIso8601ZuluString()]),
        announcement(['id' => '2', 'title' => 'New news', 'published_at' => now()->subHour()->toIso8601ZuluString()]),
        announcement(['id' => '3', 'title' => 'Gone news', 'expires_at' => now()->subMinute()->toIso8601ZuluString()]),
        announcement(['id' => '4', 'title' => 'Bio only news', 'apps' => ['biohop']]),
    ]);
    $page = $this->actingAs(member())->get('/dashboard')->assertOk();
    $page->assertSee('New news')->assertDontSee('Old news')->assertDontSee('Gone news')->assertDontSee('Bio only news');

    Cache::flush();
    hubSays([
        announcement(['id' => '2', 'title' => 'New news']),
        announcement(['id' => '9', 'level' => 'critical', 'title' => 'Act now', 'published_at' => now()->subDays(2)->toIso8601ZuluString()]),
    ]);
    $this->actingAs(member())->get('/dashboard')->assertOk()->assertSee('Act now')->assertDontSee('New news');
});

it('hides a card for a week once the member hides it, and then shows the next one', function () {
    hubSays([announcement(['id' => '2', 'title' => 'New news']), announcement(['id' => '1', 'title' => 'Older news', 'published_at' => now()->subDay()->toIso8601ZuluString()])]);
    $user = member();
    $this->actingAs($user)->get('/dashboard')->assertSee('New news');

    $this->postJson(route('reminders.dismiss'), ['key' => 'ann-2'])->assertOk();
    $this->get('/dashboard')->assertOk()->assertDontSee('New news')->assertSee('Older news');

    $this->travel(8)->days();
    $this->withSession(['last_activity_time' => now()]);
    Cache::flush();
    hubSays([announcement(['id' => '2', 'title' => 'New news'])]);
    $this->get('/dashboard')->assertOk()->assertSee('New news');
});

it('has no way to hide a critical announcement', function () {
    hubSays([announcement(['id' => '9', 'level' => 'critical', 'title' => 'Act now'])]);
    $user = member();

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Act now')->assertDontSee('data-hop-remind="ann-9"', false);
    // Even a hide request that reaches the server does not take it away.
    $this->postJson(route('reminders.dismiss'), ['key' => 'ann-9'])->assertOk();
    $this->get('/dashboard')->assertOk()->assertSee('Act now');
});

it('accepts ann-<id> keys and nothing else it does not know', function () {
    $user = member();
    $this->actingAs($user);

    $this->postJson(route('reminders.dismiss'), ['key' => 'ann-12'])->assertOk();
    foreach (['ann-', 'ann-a b', 'ann-<x>', 'ann-'.str_repeat('1', 70), 'other'] as $key) {
        $this->postJson(route('reminders.dismiss'), ['key' => $key])->assertStatus(422);
    }
    expect(DB::table('reminder_dismissals')->where('user_id', $user->id)->pluck('key')->all())->toBe(['ann-12']);
});

it('no longer carries the fixed short-links card in the code', function () {
    hubSays([]);
    config(['shortener.legacy_slug_until' => now()->addDays(30)->toDateString()]);

    $this->actingAs(member())->get('/dashboard')->assertOk()->assertDontSee('data-hop-remind="short-links-move"', false);
});
