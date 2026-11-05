<?php

use App\Models\AbuseFlag;
use App\Models\Link;
use App\Models\User;
use App\Support\PhishingShape;
use Hop\Safety\Models\DomainBlacklist;
use Hop\Safety\UrlGuard;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/*
| links:sweep-phishing: the shape of a phishing kit's address, read off the stored destination. The sample addresses
| are the ones seen on 2026-11-05 with made-up query values; the test domains .test never resolve.
*/

const KIT_URL = 'https://mjdgdqgk.fnjphdp.com/wqamhcsom?r=AbCdEf12&t=1762300000';

function sweepLink(string $slug, string $url, array $attrs = []): Link
{
    $link = Link::create(['slug' => $slug, 'target_url' => $url, 'status' => Link::STATUS_ACTIVE] + $attrs);
    if ($attrs) {
        $link->forceFill($attrs)->save();
    }

    return $link;
}

function sweepCsv(): string
{
    return storage_path('app/sweeps');
}

beforeEach(function () {
    File::deleteDirectory(sweepCsv());
    config(['shortener.guest_policy.kit_hosts' => ['fnjphdp.com', 'bgp09.icu']]);
});

afterEach(fn () => File::deleteDirectory(sweepCsv()));

it('reads the shape of the kit address and leaves ordinary addresses alone', function (string $url, bool $hit, array $signals) {
    $r = PhishingShape::assess($url);

    expect($r['hit'])->toBe($hit);
    foreach ($signals as $signal) {
        expect($r['signals'])->toContain($signal);
    }
})->with([
    'the sample' => [KIT_URL, true, ['kit_host', 'random_subdomain', 'random_path', 'tracking_params']],
    'the second kit domain' => ['https://bgp09.icu/', true, ['kit_host', 'risky_tld']],
    'same shape, other domain' => ['https://xkpqtrvz.brand-new-site.com/qwrtzpsk?r=1&t=2', true, ['random_subdomain', 'random_path', 'tracking_params']],
    'random path plus tracking on a throwaway tld' => ['https://login-check.icu/qwrtzpskm?r=1&t=2', true, ['random_path', 'tracking_params', 'risky_tld']],
    'random subdomain alone (a CDN)' => ['https://d1a2b3c4d5e6f7.cloudfront.net/lib.js', false, []],
    'random label on an unknown domain alone' => ['https://xkpqtrvz.some-site.com/', false, ['random_subdomain']],
    'readable address with r and t' => ['https://example.com/report?r=1&t=2', false, ['tracking_params']],
    'github' => ['https://github.com/navi-crwn/LinkHop', false, []],
    'country code suffix' => ['https://shop.example.co.id/produk/baju', false, []],
]);

it('knows the registrable domain', function () {
    expect(PhishingShape::registrable('mjdgdqgk.fnjphdp.com'))->toBe('fnjphdp.com')
        ->and(PhishingShape::registrable('a.b.example.co.uk'))->toBe('example.co.uk')
        ->and(PhishingShape::registrable('example.com'))->toBe('example.com')
        ->and(PhishingShape::registrable('93.184.216.34'))->toBe('93.184.216.34');
});

it('recognises the page text of the kit', function () {
    expect(PhishingShape::pageMatches('<html><title>Account Security Compliance Check</title><body><img src="tg.png"><form><label>Phone number</label><label>Two-Step Password</label><button>Yes, it was me</button></form></body></html>'))->toBeTrue()
        ->and(PhishingShape::pageMatches('<html><title>My blog</title><p>Yes, it was Mexico we visited.</p></html>'))->toBeFalse();
});

it('lists the hits grouped by domain and creator, writes a CSV and changes nothing in a dry run', function () {
    $user = User::factory()->create();
    $kit1 = sweepLink('Gi4Q3D', KIT_URL);
    $kit2 = sweepLink('kit002', 'https://bgp09.icu/zxqwrtpk?r=9&t=8', ['creator_hash' => str_repeat('a', 40)]);
    $kit3 = sweepLink('kit003', 'https://ptkqzrwm.another-kit.com/vbnmqwrt?r=1&t=1', ['user_id' => $user->id]);
    $fine = sweepLink('fine01', 'https://github.com/navi-crwn');
    $weak = sweepLink('weak01', 'https://xkpqtrvz.some-site.com/');

    $this->artisan('links:sweep-phishing')
        ->expectsOutputToContain('Scanned 5 link(s): 3 hit(s), 1 weak candidate(s).')
        ->expectsOutputToContain('fnjphdp.com')
        ->expectsOutputToContain('bgp09.icu')
        ->expectsOutputToContain('another-kit.com')
        ->expectsOutputToContain('guest:unknown')
        ->expectsOutputToContain('guest:aaaaaaaaaaaa')
        ->expectsOutputToContain("user #{$user->id} {$user->email} (1 link(s))")
        ->expectsOutputToContain('Dry run: nothing was changed.')
        ->assertSuccessful();

    foreach ([$kit1, $kit2, $kit3, $fine, $weak] as $link) {
        expect($link->fresh()->status)->toBe(Link::STATUS_ACTIVE);
    }
    expect(AbuseFlag::count())->toBe(0);
    $files = File::files(sweepCsv());
    expect($files)->toHaveCount(1);
    $csv = array_map('str_getcsv', file($files[0]->getPathname(), FILE_IGNORE_NEW_LINES));
    expect($csv[0])->toContain('link_id', 'domain', 'creator', 'reasons', 'action')
        ->and(count($csv))->toBe(5) // header + 3 hits + 1 weak candidate; the GitHub link is not listed
        ->and(implode("\n", array_map(fn ($r) => implode(',', $r), $csv)))->toContain('Gi4Q3D', 'would_disable (dry run)', 'review', 'kit_host')
        ->not->toContain('fine01');
});

it('disables the hits with --apply, records a flag on each, respects a dismissed finding and never deletes', function () {
    $user = User::factory()->create();
    $kit = sweepLink('Gi4Q3D', KIT_URL);
    $restored = sweepLink('kept01', 'https://bgp09.icu/zxqwrtpk?r=9&t=8', ['user_id' => $user->id]);
    AbuseFlag::create(['subject_type' => 'link', 'subject_id' => $restored->id, 'score' => 100, 'signals' => ['kit_host'], 'action' => 'disabled', 'status' => 'dismissed']);
    $old = sweepLink('old001', 'https://bgp09.icu/other', ['status' => Link::STATUS_INACTIVE]);
    $fine = sweepLink('fine01', 'https://github.com/navi-crwn');

    $this->artisan('links:sweep-phishing', ['--apply' => true])
        ->expectsOutputToContain('Disabled 1 link(s)')
        ->assertSuccessful();

    expect($kit->fresh()->status)->toBe(Link::STATUS_INACTIVE)
        ->and($restored->fresh()->status)->toBe(Link::STATUS_ACTIVE)
        ->and($old->fresh()->status)->toBe(Link::STATUS_INACTIVE)
        ->and($fine->fresh()->status)->toBe(Link::STATUS_ACTIVE)
        ->and(Link::count())->toBe(4)
        ->and(User::find($user->id)->isSuspended())->toBeFalse();
    $flag = AbuseFlag::where('subject_type', 'link')->where('subject_id', $kit->id)->sole();
    expect($flag->action)->toBe(AbuseFlag::ACTION_DISABLED)
        ->and($flag->status)->toBe(AbuseFlag::STATUS_OPEN)
        ->and($flag->signals)->toContain('kit_host', 'random_subdomain')
        ->and($flag->summary)->toBe('/Gi4Q3D -> mjdgdqgk.fnjphdp.com [links:sweep-phishing]')
        ->and(AbuseFlag::where('subject_id', $restored->id)->count())->toBe(1);
    // the disabled link no longer redirects
    $this->get('/l/Gi4Q3D')->assertNotFound();
});

it('adds the hit domains to the blacklist with --apply --blacklist, so new links to them are refused at once', function () {
    sweepLink('Gi4Q3D', KIT_URL);

    $this->artisan('links:sweep-phishing', ['--apply' => true, '--blacklist' => true])
        ->expectsOutputToContain('Blacklist: 2 row(s) added')
        ->assertSuccessful();

    expect(DomainBlacklist::where('domain', 'fnjphdp.com')->where('match_type', 'exact')->value('category'))->toBe('phishing')
        ->and(DomainBlacklist::where('domain', '*.fnjphdp.com')->where('match_type', 'wildcard')->exists())->toBeTrue();
    $this->post('/shorten', ['target_url' => 'https://other.fnjphdp.com/x', 'cf-turnstile-response' => 'x'])->assertSessionHasErrors('target_url');
});

it('limits the sweep to a window, to guests, and takes extra kit hosts for one run', function () {
    $user = User::factory()->create();
    sweepLink('new001', 'https://new-kit.test/page', ['created_at' => now()->subHour()]);
    sweepLink('old001', 'https://new-kit.test/page', ['created_at' => now()->subDays(3)]);
    sweepLink('acct01', 'https://new-kit.test/page', ['user_id' => $user->id, 'created_at' => now()->subHour()]);

    $this->artisan('links:sweep-phishing', ['--since' => '1d', '--guest-only' => true, '--hosts' => 'new-kit.test'])
        ->expectsOutputToContain(': 1 hit(s), 0 weak candidate(s).')
        ->assertSuccessful();
    $this->artisan('links:sweep-phishing', ['--since' => 'yesterday-ish'])->assertFailed();
    // without the extra host, nothing about new-kit.test looks like the kit
    $this->artisan('links:sweep-phishing')->expectsOutputToContain('0 hit(s)')->assertSuccessful();
});

it('fetches weak candidates through UrlGuard with --fetch and counts a page that reads like the kit as a hit', function () {
    $this->mock(UrlGuard::class, function ($mock) {
        $mock->shouldReceive('resolve')->andReturnUsing(fn (string $url) => ['scheme' => 'https', 'host' => (string) parse_url($url, PHP_URL_HOST), 'port' => 443, 'ips' => ['93.184.216.34']]);
        $mock->shouldReceive('curlResolve')->andReturn(['host:443:93.184.216.34']);
    });
    Http::fake([
        'https://xkpqtrvz.some-site.com/*' => Http::response('<title>Account Security Compliance Check</title><button>Yes, it was me</button>'),
        'https://zxqwrtpk.other-site.com/*' => Http::response('<title>Hello</title>'),
    ]);
    $bad = sweepLink('weak01', 'https://xkpqtrvz.some-site.com/');
    $ok = sweepLink('weak02', 'https://zxqwrtpk.other-site.com/');
    sweepLink('fine01', 'https://github.com/navi-crwn');

    $this->artisan('links:sweep-phishing', ['--fetch' => true, '--apply' => true])
        ->expectsOutputToContain('1 hit(s), 1 weak candidate(s), 2 fetched')
        ->assertSuccessful();

    expect($bad->fresh()->status)->toBe(Link::STATUS_INACTIVE)
        ->and($ok->fresh()->status)->toBe(Link::STATUS_ACTIVE)
        ->and(AbuseFlag::where('subject_id', $bad->id)->value('signals'))->toContain('page_text');
    Http::assertSentCount(2);
});

it('does not fetch an address UrlGuard refuses', function () {
    $this->mock(UrlGuard::class, fn ($mock) => $mock->shouldReceive('resolve')->andReturnNull());
    Http::fake();
    sweepLink('weak01', 'https://xkpqtrvz.internal-site.com/');

    $this->artisan('links:sweep-phishing', ['--fetch' => true])->expectsOutputToContain('0 hit(s), 1 weak candidate(s), 1 fetched')->assertSuccessful();

    Http::assertNothingSent();
});
