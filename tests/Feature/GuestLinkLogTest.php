<?php

use App\Models\GuestLinkLog;
use App\Models\Link;
use App\Models\User;
use App\Support\GuestDestination;
use Illuminate\Support\Facades\DB;

/*
| Guest links across days: a record of who made what (a keyed hash of the address, never the address), a cap per
| hour and per rolling day, and a destination that many different connections reach in a short window, which is
| held for review instead of being suspended. config/shortener.php, guest_policy.
*/

function guestFrom(string $ip, string $url)
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->post('/shorten', ['target_url' => $url, 'cf-turnstile-response' => 'x']);
}

beforeEach(function () {
    config([
        'shortener.guest_policy.per_ip_per_day' => 0,   // the old calendar-day cap is not what these tests measure
        'shortener.guest_policy.per_ip_per_hour' => 0,
        'shortener.guest_policy.per_ip_per_rolling_day' => 0,
        'shortener.guest_policy.burst_ips' => 0,
        // Keep the outside world out: allowlist everything, no RDAP, no shape refusal.
        'shortener.guest_policy.allowlist' => ['github.com', 'example.com', 'my-little-shop.test'],
        'shortener.guest_policy.domain_age_lookup' => false,
        'shortener.guest_policy.pending_unknown_hosts' => false,
        'shortener.guest_policy.refuse_phishing_shape' => false,
        'shortener.guest_policy.refuse_random_subdomain' => false,
    ]);
});

it('records every guest link with a hashed connection and a normalized destination, never the address', function () {
    guestFrom('203.0.113.10', 'https://github.com/navi-crwn?utm_source=x&fbclid=abc#readme')->assertSessionHasNoErrors();

    $row = GuestLinkLog::sole();
    expect($row->creator_hash)->toHaveLength(40)
        ->and($row->creator_hash)->not->toContain('203.0.113')
        ->and($row->destination)->toBe('github.com/navi-crwn')
        ->and($row->slug)->toBe(Link::sole()->slug);

    // nothing anywhere holds the raw address
    expect(json_encode(DB::table('guest_link_logs')->get()))->not->toContain('203.0.113.10');
});

it('normalizes a destination to host and path, without the tracking query or the fragment', function (string $url, string $expected) {
    expect(GuestDestination::normalize($url))->toBe($expected);
})->with([
    'tracking query' => ['https://Example.COM/a/b?utm_source=x&gclid=1#frag', 'example.com/a/b'],
    'trailing slash' => ['https://example.com/a/', 'example.com/a'],
    'port and www' => ['https://www.example.com:8443/x?q=1', 'example.com/x'],
    'root' => ['https://example.com/', 'example.com'],
    'no path' => ['https://example.com', 'example.com'],
]);

it('caps a connection per hour and per rolling day, across midnight', function () {
    config([
        'shortener.guest_policy.per_ip_per_hour' => 2,
        'shortener.guest_policy.per_ip_per_rolling_day' => 3,
    ]);

    // Two right before midnight, one right after: the calendar day changed, the rolling day did not.
    $this->travelTo(now()->setTime(23, 30));
    guestFrom('203.0.113.20', 'https://github.com/a')->assertSessionHasNoErrors();
    guestFrom('203.0.113.20', 'https://github.com/b')->assertSessionHasNoErrors();
    // 61 minutes after the first two: past midnight, and past the hour they occupied.
    $this->travelTo(now()->addMinutes(61));

    guestFrom('203.0.113.20', 'https://github.com/c')->assertSessionHasNoErrors();
    guestFrom('203.0.113.20', 'https://github.com/d')
        ->assertSessionHasErrors(['target_url' => __('Guests can make :max short links a day from one connection. Create a free account to make more.', ['max' => 3])]);

    // Three hours after the first two, the hourly cap has room again but the rolling day still counts all three
    // links made so far: the cap stands.
    $this->travelTo(now()->addHours(3));
    guestFrom('203.0.113.20', 'https://github.com/e')
        ->assertSessionHasErrors(['target_url' => __('Guests can make :max short links a day from one connection. Create a free account to make more.', ['max' => 3])]);

    // A day after the first links, the rolling window has moved past them, and the hourly cap has room again.
    $this->travelTo(now()->addHours(21));
    guestFrom('203.0.113.20', 'https://github.com/g')->assertSessionHasNoErrors();
    guestFrom('203.0.113.20', 'https://github.com/h')->assertSessionHasNoErrors();
    guestFrom('203.0.113.20', 'https://github.com/i')
        ->assertSessionHasErrors(['target_url' => __('Guests can make :max short links an hour from one connection. Create a free account to make more.', ['max' => 2])]);

    expect(Link::count())->toBe(5);
});

it('holds new guest links to a destination that many connections reached in a short window', function () {
    config(['shortener.guest_policy.burst_ips' => 3, 'shortener.guest_policy.burst_window_minutes' => 60]);

    // Three different connections to the same destination inside the window: a burst.
    foreach (['203.0.113.31', '203.0.113.32', '203.0.113.33'] as $ip) {
        guestFrom($ip, 'https://example.com/claim?utm_source='.$ip)->assertSessionHasNoErrors();
    }

    // The next connection to that destination is held, not suspended: a warning page and a flag for review.
    guestFrom('203.0.113.34', 'https://example.com/claim')->assertSessionHasNoErrors();
    $held = Link::where('target_url', 'like', 'https://example.com/claim%')->latest('id')->first();
    expect($held->safety_status)->toBe(Link::SAFETY_PENDING);

    $this->assertDatabaseHas('abuse_flags', ['subject_type' => 'link', 'subject_id' => $held->id]);
    // The four links already made are untouched: nothing is disabled by the burst rule.
    expect(Link::where('status', '!=', Link::STATUS_ACTIVE)->count())->toBe(0);
});

it('does not hold a legitimate destination: few connections, or one connection many times', function () {
    config(['shortener.guest_policy.burst_ips' => 3, 'shortener.guest_policy.burst_window_minutes' => 60]);

    // One connection making several links to the same site is a person, not a burst.
    foreach (['a', 'b', 'c', 'd'] as $slug) {
        guestFrom('203.0.113.41', 'https://github.com/'.$slug)->assertSessionHasNoErrors();
    }
    // Two connections to another destination is under the threshold.
    guestFrom('203.0.113.42', 'https://example.com/two')->assertSessionHasNoErrors();
    guestFrom('203.0.113.43', 'https://example.com/two')->assertSessionHasNoErrors();
    guestFrom('203.0.113.44', 'https://example.com/two')->assertSessionHasNoErrors();

    expect(Link::where('safety_status', Link::SAFETY_PENDING)->count())->toBe(0)
        ->and(Link::count())->toBe(7);
});

it('does not count the same connection twice toward a burst', function () {
    config(['shortener.guest_policy.burst_ips' => 2, 'shortener.guest_policy.burst_window_minutes' => 60]);

    guestFrom('203.0.113.51', 'https://example.com/one')->assertSessionHasNoErrors();
    guestFrom('203.0.113.51', 'https://example.com/one')->assertSessionHasNoErrors();

    guestFrom('203.0.113.52', 'https://example.com/one')->assertSessionHasNoErrors();

    expect(Link::where('safety_status', Link::SAFETY_PENDING)->count())->toBe(0);
});

it('keeps the log for fourteen days and deletes the rest with the daily task', function () {
    config(['shortener.guest_policy.retention_days' => 14]);

    $old = GuestLinkLog::create([
        'creator_hash' => str_repeat('a', 40),
        'destination' => 'example.com/old',
        'slug' => 'old1',
        'created_at' => now()->subDays(20),
    ]);
    $fresh = GuestLinkLog::create([
        'creator_hash' => str_repeat('b', 40),
        'destination' => 'example.com/new',
        'slug' => 'new1',
        'created_at' => now()->subDays(2),
    ]);

    $this->artisan('links:prune-guest-log')->assertSuccessful();

    expect(GuestLinkLog::find($old->id))->toBeNull()
        ->and(GuestLinkLog::find($fresh->id))->not->toBeNull();
});

it('does not apply any of it to an account', function () {
    config([
        'shortener.guest_policy.per_ip_per_rolling_day' => 1,
        'shortener.guest_policy.burst_ips' => 1,
    ]);
    $user = User::factory()->create();

    foreach (range(1, 3) as $i) {
        $this->actingAs($user)->postJson('/api/shorten-web', ['url' => 'https://example.com/'.$i])->assertCreated();
    }

    expect(GuestLinkLog::count())->toBe(0)
        ->and(Link::where('safety_status', Link::SAFETY_PENDING)->count())->toBe(0);
});
