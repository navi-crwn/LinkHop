<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\Tag;
use App\Models\User;
use App\Support\DashboardStats;
use Illuminate\Support\Facades\DB;

// The stats count by calendar day (UTC), and the clicks below are made "one hour ago": between 00:00 and 01:00 that is
// yesterday. Pin the clock to midday of the current day so the tests do not depend on when they run.
beforeEach(fn () => $this->travelTo(now()->setTime(12, 0)));

function dashUser(): User
{
    return User::factory()->create();
}

function dashLink(User $user, string $slug, int $clicksToday = 0, int $clicksLastWeek = 0): Link
{
    $link = Link::create(['user_id' => $user->id, 'slug' => $slug, 'target_url' => 'https://example.com/'.$slug, 'status' => 'active']);
    foreach (range(1, $clicksToday) as $i) {
        if ($clicksToday) {
            LinkClick::create(['link_id' => $link->id, 'clicked_at' => now()->subHours(1)]);
        }
    }
    foreach (range(1, $clicksLastWeek) as $i) {
        if ($clicksLastWeek) {
            LinkClick::create(['link_id' => $link->id, 'clicked_at' => now()->subDays(9)]);
        }
    }

    return $link;
}

test('this week is counted against the week before, per day', function () {
    $user = dashUser();
    dashLink($user, 'one', 3, 2);
    dashLink(dashUser(), 'other', 5, 5); // someone else's clicks never count
    $week = DashboardStats::week($user);
    expect($week['total'])->toBe(3)->and($week['previous'])->toBe(2)->and($week['delta'])->toBe(50)
        ->and(count($week['week']))->toBe(7)->and($week['week'][6])->toBe(3);
});

test('there is no percentage when the week before had no clicks', function () {
    $user = dashUser();
    dashLink($user, 'fresh', 2, 0);
    expect(DashboardStats::week($user)['delta'])->toBeNull();
});

test('each link gets a seven-day line', function () {
    $user = dashUser();
    $a = dashLink($user, 'a', 2, 0);
    $b = dashLink($user, 'b', 0, 0);
    $lines = DashboardStats::sparklines([$a->id, $b->id]);
    expect($lines[$a->id])->toHaveCount(7)->and($lines[$a->id][6])->toBe(2)->and(array_sum($lines[$b->id]))->toBe(0);
    expect(DashboardStats::sparklines([]))->toBe([]);
});

test('the checklist follows what the account has done', function () {
    $user = dashUser();
    expect(DashboardStats::checklist($user)['done'])->toBe(0);
    $link = dashLink($user, 'start', 1, 0);
    $link->update(['clicks' => 1, 'password_hash' => bcrypt('x'), 'custom_title' => 'Hello']);
    Tag::create(['user_id' => $user->id, 'name' => 'x']);
    $c = DashboardStats::checklist($user);
    $done = collect($c['items'])->pluck('done', 'label');
    expect($c['done'])->toBe(5)
        ->and($done['Create your first link'])->toBeTrue()
        ->and($done['Make a QR code your own'])->toBeFalse();
});

test('the dashboard shows the week, the checklist and a line per link', function () {
    $user = dashUser();
    dashLink($user, 'shown', 4, 0);
    $this->actingAs($user)->get('/dashboard')->assertOk()
        ->assertSee('Clicks this week')->assertSee('Get the most out of LinkHop')->assertSee('/l/shown')->assertSee('polyline', false);
});

test('searching my links works on a table without a title column', function () {
    $user = dashUser();
    dashLink($user, 'findme', 0, 0)->update(['custom_title' => 'Spring sale']);
    dashLink($user, 'other', 0, 0);
    DB::enableQueryLog();
    $this->actingAs($user)->get('/links?q=Spring')->assertOk()->assertSee('/l/findme')->assertDontSee('/l/other');
    $sql = collect(DB::getQueryLog())->pluck('query')->implode(' ');
    expect($sql)->toContain('custom_title')->and(preg_match('/[`"]title[`"] like/i', $sql))->toBe(0);
});

test('analytics compares with the period before and draws the busiest hours', function () {
    $user = dashUser();
    $link = dashLink($user, 'trend', 0, 0);
    LinkClick::create(['link_id' => $link->id, 'clicked_at' => now()->subHours(2)]);
    LinkClick::create(['link_id' => $link->id, 'clicked_at' => now()->subHours(3)]);
    LinkClick::create(['link_id' => $link->id, 'clicked_at' => now()->subHours(30)]);
    $this->actingAs($user)->get('/analytics?hours=24')->assertOk()
        ->assertViewHas('previousClicks', 1)->assertViewHas('heatmap', fn ($h) => count($h) === 7 && count($h[0]) === 24 && array_sum(array_map('array_sum', $h)) === 3)
        ->assertSee('Busiest hours')->assertSee('+100% against the period before');
});
