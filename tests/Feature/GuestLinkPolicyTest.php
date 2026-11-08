<?php

use App\Models\AbuseFlag;
use App\Models\HopApp;
use App\Models\Link;
use App\Models\User;
use App\Services\DomainAge;
use App\Support\GuestDigest;
use App\Support\VisitorHash;
use Hop\Ui\Locales;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
| The guest policy (config/shortener.php, guest_policy): what a link made without an account may point at. Accounts
| are not touched by any of it.
*/

function guestShorten(string $url, array $extra = [])
{
    return test()->post('/shorten', ['target_url' => $url, 'cf-turnstile-response' => 'x'] + $extra);
}

function rdapResponse(?string $registered)
{
    return Http::response($registered === null ? ['events' => []] : ['events' => [['eventAction' => 'registration', 'eventDate' => $registered]]]);
}

beforeEach(function () {
    config(['shortener.guest_policy.kit_hosts' => ['fnjphdp.com', 'bgp09.icu']]);
});

it('takes a guest link to an allowlisted site as it is', function () {
    guestShorten('https://github.com/navi-crwn/LinkHop')->assertRedirect(route('home'))->assertSessionHasNoErrors();

    $link = Link::sole();
    expect($link->safety_status)->toBeNull()
        ->and($link->user_id)->toBeNull()
        ->and($link->creator_hash)->toBe(VisitorHash::make('127.0.0.1'));
    $this->get('/l/'.$link->slug)->assertRedirect('https://github.com/navi-crwn/LinkHop');
});

it('marks a guest link to an unknown site pending and shows the warning page until the scan clears it', function () {
    guestShorten('https://my-little-shop.test/sale')->assertSessionHasNoErrors();
    $link = Link::sole();
    expect($link->safety_status)->toBe(Link::SAFETY_PENDING);

    $page = $this->get('/l/'.$link->slug)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $page->assertSee('Unverified destination')->assertSee('my-little-shop.test')->assertSee('created by a guest')->assertDontSee('Continue to my-little-shop.test"');
    expect($link->fresh()->clicks)->toBe(0);

    // "Continue" is a POST, remembered for the session, then the normal redirect
    $this->post('/l/'.$link->slug, ['unverified_confirm' => 'yes'])->assertOk();
    $this->get('/l/'.$link->slug)->assertRedirect('https://my-little-shop.test/sale');

    // the hourly scan finds nothing and clears it: no page any more, for anyone
    $this->artisan('abuse:scan', ['--since' => '2h'])->assertSuccessful();
    expect($link->fresh()->safety_status)->toBe(Link::SAFETY_SAFE)
        ->and($link->fresh()->last_safety_scan_at)->not->toBeNull();
    $this->flushSession();
    $this->get('/l/'.$link->slug)->assertRedirect('https://my-little-shop.test/sale');
});

it('leaves a pending link pending in a dry-run scan and marks it flagged when the scan disables it', function () {
    $kept = Link::create(['slug' => 'pend01', 'target_url' => 'https://my-little-shop.test/', 'status' => 'active', 'safety_status' => 'pending']);
    $bad = Link::create(['slug' => 'pend02', 'target_url' => 'https://mjdgdqgk.fnjphdp.com/wqamhcsom?r=1&t=2', 'status' => 'active', 'safety_status' => 'pending']);

    $this->artisan('abuse:scan', ['--full' => true, '--dry-run' => true])->assertSuccessful();
    expect($kept->fresh()->safety_status)->toBe('pending')->and($bad->fresh()->status)->toBe('active');

    $this->artisan('abuse:scan', ['--full' => true])->assertSuccessful();
    expect($kept->fresh()->safety_status)->toBe('safe')
        ->and($bad->fresh()->status)->toBe('inactive')
        ->and($bad->fresh()->safety_status)->toBe('flagged')
        ->and(AbuseFlag::where('subject_id', $bad->id)->value('signals'))->toContain('phishing_shape');
});

it('clears a pending link the hourly scan had missed, however old, while the window still bounds the rest', function () {
    $missed = Link::create(['slug' => 'pend01', 'target_url' => 'https://my-little-shop.test/', 'status' => 'active', 'safety_status' => 'pending']);
    $oldKit = Link::create(['slug' => 'kit001', 'target_url' => 'https://mjdgdqgk.fnjphdp.com/wqamhcsom?r=1&t=2', 'status' => 'active']);
    DB::table('links')->whereIn('id', [$missed->id, $oldKit->id])->update(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);

    $this->artisan('abuse:scan', ['--since' => '2h'])->assertSuccessful();

    expect($missed->fresh()->safety_status)->toBe('safe')
        ->and($oldKit->fresh()->status)->toBe('active'); // outside the window, not pending: left for the nightly full scan
});

it('disables an incident kit path even without a known host or random subdomain', function () {
    $user = User::factory()->create();
    $link = Link::create([
        'slug' => 'incid1',
        'target_url' => 'https://pylinss.jehenmy.com/wqamhcsom?r=gnqhLD&t=1793793002',
        'status' => 'active',
        'user_id' => $user->id,
    ]);

    $this->artisan('abuse:scan', ['--full' => true])->assertSuccessful();

    expect($link->fresh()->status)->toBe('inactive')
        ->and(AbuseFlag::where('subject_id', $link->id)->value('signals'))->toContain('phishing_shape', 'kit_path');
});
it('scores the shape of the kit address for everyone, so the scan disables an account link to it too', function () {
    $user = User::factory()->create();
    $link = Link::create(['slug' => 'acct01', 'target_url' => 'https://xkpqtrvz.brand-new-site.com/qwrtzpsk?r=1&t=2', 'status' => 'active', 'user_id' => $user->id]);

    $this->artisan('abuse:scan', ['--full' => true])->assertSuccessful();

    expect($link->fresh()->status)->toBe('inactive')
        ->and($link->fresh()->safety_status)->toBeNull()
        ->and(AbuseFlag::where('subject_id', $link->id)->value('score'))->toBe(90)
        ->and(AbuseFlag::where('subject_id', $link->id)->value('signals'))->toContain('phishing_shape', 'random_subdomain');
});

it('refuses a guest link whose destination looks like the kit, and one on a random subdomain', function (string $url) {
    guestShorten($url)->assertSessionHasErrors('target_url');
    expect(Link::count())->toBe(0);
})->with([
    'the kit' => ['https://mjdgdqgk.fnjphdp.com/wqamhcsom?r=1&t=2'],
    'the shape on a new domain' => ['https://xkpqtrvz.brand-new-site.com/qwrtzpsk?r=1&t=2'],
    'a random subdomain alone' => ['https://xkpqtrvz.some-site.com/page'],
    'the kit path on an ordinary domain' => ['https://example.com/wqamhcsom?r=1&t=2'],
]);

it('says that an account is needed, in the visitor language', function () {
    $catalog = json_decode((string) file_get_contents(lang_path('id.json')), true);
    $key = 'This destination is on a very new or unknown site, so it cannot be shortened as a guest. Sign in with a free HOP ID to shorten it.';

    $this->post('/shorten?lang=id', ['target_url' => 'https://xkpqtrvz.some-site.com/page', 'cf-turnstile-response' => 'x'])
        ->assertSessionHasErrors(['target_url' => $catalog[$key]]);
});

it('refuses a guest link to a domain registered days ago, allows an old one, and remembers the registry answer', function () {
    config(['shortener.guest_policy.domain_age_lookup' => true, 'shortener.guest_policy.min_domain_age_days' => 30]);
    Http::fake([
        'rdap.org/domain/fresh-site.test' => rdapResponse(now()->subDays(3)->toIso8601String()),
        'rdap.org/domain/old-site.test' => rdapResponse('2015-04-02T10:00:00Z'),
    ]);

    guestShorten('https://shop.fresh-site.test/sale')->assertSessionHasErrors('target_url');
    guestShorten('https://fresh-site.test/other')->assertSessionHasErrors('target_url');
    guestShorten('https://old-site.test/page')->assertSessionHasNoErrors();

    expect(Link::count())->toBe(1)
        ->and(Link::sole()->safety_status)->toBe('pending')
        ->and(app(DomainAge::class)->ageInDays('shop.fresh-site.test'))->toBe(3);
    Http::assertSentCount(2); // one lookup per registrable domain
});

it('treats a registry that does not answer as young by default, and as unknown when told so', function () {
    config(['shortener.guest_policy.domain_age_lookup' => true, 'shortener.guest_policy.min_domain_age_days' => 30]);
    Http::fake(['rdap.org/*' => Http::response('', 404)]);

    guestShorten('https://silent-site.test/page')->assertSessionHasErrors('target_url');

    config(['shortener.guest_policy.unknown_age_is_young' => false]);
    Cache::flush();
    guestShorten('https://silent-site.test/page')->assertSessionHasNoErrors();
    expect(Link::count())->toBe(1);
});

it('does not ask the registry for an allowlisted site, and never on the account path', function () {
    config(['shortener.guest_policy.domain_age_lookup' => true, 'shortener.guest_policy.min_domain_age_days' => 30]);
    Http::fake();
    $user = User::factory()->create();

    guestShorten('https://github.com/navi-crwn')->assertSessionHasNoErrors();
    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => 'https://xkpqtrvz.brand-new-site.com/qwrtzpsk?r=1&t=2'])->assertUnprocessable();

    Http::assertNothingSent();
    expect(Link::where('user_id', $user->id)->count())->toBe(0);
});

it('caps the guest links one connection may make in a day, next to the per-minute limiter', function () {
    config(['shortener.guest_policy.per_ip_per_day' => 2]);

    guestShorten('https://github.com/a')->assertSessionHasNoErrors();
    guestShorten('https://github.com/b')->assertSessionHasNoErrors();
    guestShorten('https://github.com/c')->assertSessionHasErrors(['target_url' => __('Guests can make :max short links a day from one connection. Create a free account to make more.', ['max' => 2])]);
    expect(Link::count())->toBe(2);

    // another connection is not affected; the cap turns off at 0
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->post('/shorten', ['target_url' => 'https://github.com/d', 'cf-turnstile-response' => 'x'])->assertSessionHasNoErrors();
    config(['shortener.guest_policy.per_ip_per_day' => 0]);
    guestShorten('https://github.com/e')->assertSessionHasNoErrors();
    expect(Link::count())->toBe(4);
});

it('does not touch an account: unknown host, random subdomain, no cap, no warning page', function () {
    config(['shortener.guest_policy.per_ip_per_day' => 1]);
    $user = User::factory()->create();

    foreach (['https://my-little-shop.test/sale', 'https://xkpqtrvz.some-site.com/page', 'https://another.test/'] as $url) {
        $this->actingAs($user)->postJson('/api/shorten-web', ['url' => $url])->assertCreated();
    }
    $link = Link::where('user_id', $user->id)->where('target_url', 'https://my-little-shop.test/sale')->sole();
    expect($link->safety_status)->toBeNull()->and($link->creator_hash)->toBeNull();
    $this->get('/l/'.$link->slug)->assertRedirect('https://my-little-shop.test/sale');
});

it('can be switched off, and the warning page can be kept off while refusals stay on', function () {
    config(['shortener.guest_policy.pending_unknown_hosts' => false]);
    guestShorten('https://my-little-shop.test/sale')->assertSessionHasNoErrors();
    expect(Link::sole()->safety_status)->toBeNull();
    guestShorten('https://xkpqtrvz.some-site.com/page')->assertSessionHasErrors('target_url');

    config(['shortener.guest_policy.enabled' => false]);
    guestShorten('https://xkpqtrvz.some-site.com/page')->assertSessionHasNoErrors();
    expect(Link::count())->toBe(2);
});

it('shows the warning page in the nine languages', function (string $lang) {
    $link = Link::create(['slug' => 'pend01', 'target_url' => 'https://my-little-shop.test/', 'status' => 'active', 'safety_status' => 'pending']);
    $catalog = json_decode((string) file_get_contents(lang_path("{$lang}.json")), true);

    $html = $this->get("/l/pend01?lang={$lang}")->assertOk()->getContent();

    expect($html)->toContain('<html lang="'.$lang.'"')
        ->toContain(e($catalog['Unverified destination']))
        ->toContain(e($catalog['This short link was created by a guest and points to a site LinkHop does not know yet. It has not been checked.']))
        ->toContain(e(str_replace(':host', 'my-little-shop.test', $catalog['Continue to :host'])));
    if ($lang !== 'en') {
        expect($html)->not->toContain('It has not been checked.');
    }
})->with(fn () => Locales::codes());

it('tells Imperium about pending links in the manifest row and the statistics', function () {
    HopApp::factory()->imperium()->create();
    Link::create(['slug' => 'pend01', 'target_url' => 'https://my-little-shop.test/', 'status' => 'active', 'safety_status' => 'pending']);

    $digest = app(GuestDigest::class)->build();
    expect($digest['guest_links'])->toBe(1)->and($digest['pending'])->toBe(1);
});
