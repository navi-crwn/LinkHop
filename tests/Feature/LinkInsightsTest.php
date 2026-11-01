<?php

use App\Mail\LinkDestinationBroken;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use App\Services\LinkService;
use App\Services\UserAgentService;
use App\Support\ClickSource;
use App\Support\QrPng;
use App\Support\VisitorHash;
use Hop\Safety\UrlGuard;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

const IG = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/21A329 Instagram 300.0.0.12.109 (iPhone14,2; iOS 17_0; en_US; en-US; scale=3.00; 1170x2532; 123456789)';
const SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

function insightLink(array $attrs = []): Link
{
    static $n = 0;
    $n++;
    $user = $attrs['__user'] ?? User::factory()->create();
    unset($attrs['__user']);

    return Link::create($attrs + ['user_id' => $user->id, 'slug' => 'ins'.$n, 'target_url' => 'https://example.com/ins'.$n, 'status' => 'active']);
}

// ---- user agents ---------------------------------------------------------------------------------------------

dataset('in-app browsers', [
    'Instagram' => [IG, 'Instagram'],
    'Facebook iOS' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/21A329 [FBAN/FBIOS;FBAV/430.0.0.0;FBBV/1;FBDV/iPhone14,2;FBMD/iPhone;FBSN/iOS;FBSV/17.0;FBSS/3;FBID/phone;FBLC/en_US;FBOP/5]', 'Facebook'],
    'Facebook Android' => ['Mozilla/5.0 (Linux; Android 13; Pixel 7 Build/TQ3A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/120.0.0.0 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/430.0.0.0;]', 'Facebook'],
    'Messenger' => ['Mozilla/5.0 (Linux; Android 13; Pixel 7 Build/TQ3A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/120.0.0.0 Mobile Safari/537.36 [FB_IAB/MESSENGER;FBAV/400.0.0.0;]', 'Messenger'],
    'TikTok' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 BytedanceWebview/d8a21c6 TikTok 30.1.0', 'TikTok'],
    'LINE' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Safari Line/13.19.0', 'LINE'],
    'WeChat' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.43(0x18002b2e) NetType/WIFI Language/zh_CN', 'WeChat'],
    'Snapchat' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Snapchat/12.60.0.38 (like Safari/8617.1.17.10.9, iOS 17.0; gzip)', 'Snapchat'],
    'X' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Twitter for iPhone/10.10', 'X (Twitter)'],
    'LinkedIn' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 LinkedInApp/9.30.1', 'LinkedIn'],
    'unknown Android WebView' => ['Mozilla/5.0 (Linux; Android 13; Pixel 7 Build/TQ3A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/120.0.0.0 Mobile Safari/537.36', UserAgentService::IN_APP_OTHER],
    'unknown iPhone web view' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148', UserAgentService::IN_APP_OTHER],
]);

test('built-in browsers of apps are named, and are people', function (string $ua, string $app) {
    $parsed = app(UserAgentService::class)->parse($ua);

    expect($parsed['in_app'])->toBe($app)->and($parsed['bot'])->toBeFalse();
})->with('in-app browsers');

test('ordinary browsers are not in-app browsers', function (string $ua) {
    expect(app(UserAgentService::class)->parse($ua)['in_app'])->toBeNull();
})->with([
    'Safari' => SAFARI,
    'Chrome on iOS' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0.6099.119 Mobile/15E148 Safari/604.1',
    'Chrome on Windows' => CHROME,
    'Chrome on Android' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
]);

test('link previewers, crawlers and http tools are bots', function (string $ua, string $name) {
    $parsed = app(UserAgentService::class)->parse($ua);

    expect($parsed['bot'])->toBeTrue()->and($parsed['bot_name'])->toBe($name)->and($parsed['in_app'])->toBeNull();
})->with([
    'WhatsApp' => ['WhatsApp/2.23.20.0 A', 'WhatsApp'],
    'Telegram' => ['TelegramBot (like TwitterBot)', 'Telegram'],
    'Slack' => ['Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)', 'Slack'],
    'Discord' => ['Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)', 'Discord'],
    'Facebook crawler' => ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', 'Facebook'],
    'Twitterbot' => ['Twitterbot/1.0', 'X (Twitter)'],
    'LinkedIn' => ['LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)', 'LinkedIn'],
    'Skype' => ['SkypeUriPreview Preview/0.5 skype-url-preview@microsoft.com', 'Skype'],
    'curl' => ['curl/8.4.0', 'HTTP tool'],
    'python' => ['python-requests/2.31.0', 'HTTP tool'],
    'headless Chrome' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0.0.0 Safari/537.36', 'HTTP tool'],
    'an unlisted crawler' => ['Mozilla/5.0 (compatible; ExampleBot/1.0; +https://example.org/bot)', 'Bot'],
]);

test('a phone called Cubot is not a bot', function () {
    $ua = 'Mozilla/5.0 (Linux; Android 11; CUBOT_X30) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';

    expect(app(UserAgentService::class)->parse($ua)['bot'])->toBeFalse();
});

test('previewers neither count nor use up a limit, while an in-app visit does and is named', function () {
    $link = insightLink(['max_clicks' => 5]);
    $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get('/l/'.$link->slug)->assertRedirect($link->target_url);
    $this->withHeader('User-Agent', 'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)')->get('/l/'.$link->slug)->assertRedirect($link->target_url);
    expect(LinkClick::count())->toBe(0)->and($link->fresh()->clicks)->toBe(0);

    $this->withHeader('User-Agent', IG)->get('/l/'.$link->slug)->assertRedirect($link->target_url);
    expect(LinkClick::count())->toBe(1)
        ->and(LinkClick::first()->in_app)->toBe('Instagram')
        ->and($link->fresh()->clicks)->toBe(1);
});

// ---- unique visitors -----------------------------------------------------------------------------------------

test('the visitor hash is stable within a day and different the next, and holds no raw IP', function () {
    $noon = now()->setTime(12, 0);
    $a = VisitorHash::daily('203.0.113.9', CHROME, $noon);

    expect($a)->toBe(VisitorHash::daily('203.0.113.9', CHROME, $noon->copy()->setTime(23, 59)))
        ->and($a)->not->toBe(VisitorHash::daily('203.0.113.9', CHROME, $noon->copy()->addDay()))
        ->and($a)->not->toBe(VisitorHash::daily('203.0.113.10', CHROME, $noon))
        ->and($a)->not->toBe(VisitorHash::daily('203.0.113.9', SAFARI, $noon))
        ->and($a)->not->toContain('203.0.113')
        ->and(strlen($a))->toBe(40)
        ->and(VisitorHash::daily(null, CHROME))->toBeNull()
        ->and(VisitorHash::daily('not-an-ip', CHROME))->toBeNull();
});

test('unique visitors count a person once a day, per link and overall', function () {
    $user = User::factory()->create();
    $one = insightLink(['__user' => $user]);
    $two = insightLink(['__user' => $user]);
    $visit = fn (Link $link, string $ip, string $ua = CHROME) => $this->withHeaders(['User-Agent' => $ua])->withServerVariables(['REMOTE_ADDR' => $ip])->get('/l/'.$link->slug);

    $visit($one, '203.0.113.1');
    $visit($one, '203.0.113.1');   // same person, same day
    $visit($one, '203.0.113.2');
    $visit($two, '203.0.113.1');   // same person on another link
    $visit($one, '203.0.113.1', SAFARI); // same address, another browser

    expect(DB::table('link_clicks')->pluck('visitor_hash')->filter()->count())->toBe(5)
        ->and(DB::table('link_clicks')->pluck('visitor_hash')->implode(','))->not->toContain('203.0.113')
        ->and(DB::table('link_clicks')->distinct()->count('visitor_hash'))->toBe(3);

    // The same person tomorrow is a new visitor (the hash rotates).
    $this->travel(1)->days();
    Cache::flush();
    $visit($one, '203.0.113.1');
    expect(LinkClick::where('link_id', $one->id)->distinct()->count('visitor_hash'))->toBe(4);
    $this->travelBack();

    $this->actingAs($user);
    $all = $this->get(route('analytics', ['hours' => 72]))->assertOk()->assertSee('Unique visitors');
    expect($all->viewData('totalClicks'))->toBe(6)->and($all->viewData('insights')['unique'])->toBe(4);
    $perLink = $this->get(route('links.show', $one))->assertOk();
    expect($perLink->viewData('insights')['unique'])->toBe(4);
});

test('the dashboard shows unique visitors for the week', function () {
    $user = User::factory()->create();
    $link = insightLink(['__user' => $user]);
    foreach (['203.0.113.1', '203.0.113.1', '203.0.113.2'] as $ip) {
        $this->withHeader('User-Agent', CHROME)->withServerVariables(['REMOTE_ADDR' => $ip])->get('/l/'.$link->slug);
    }

    $page = $this->actingAs($user)->get(route('dashboard'))->assertOk();
    expect($page->viewData('week')['unique'])->toBe(2);
    $page->assertSee('2 unique visitors');
});

// ---- QR source -----------------------------------------------------------------------------------------------

test('?src=qr is recorded, never reaches the destination, and anything unknown is a direct click', function () {
    $link = insightLink(['target_url' => 'https://example.com/landing?ref=a']);
    $go = fn (string $query) => $this->withHeader('User-Agent', CHROME)->get('/l/'.$link->slug.$query);

    $go('?src=qr')->assertRedirect('https://example.com/landing?ref=a');
    $go('?src=NFC')->assertRedirect('https://example.com/landing?ref=a');
    $go('')->assertRedirect('https://example.com/landing?ref=a');
    $go('?src=newsletter')->assertRedirect('https://example.com/landing?ref=a');
    $go('?src[]=qr')->assertRedirect('https://example.com/landing?ref=a');

    expect(LinkClick::orderBy('id')->pluck('source')->all())->toBe(['qr', 'nfc', 'direct', 'direct', 'direct']);
});

test('the source survives the password step', function () {
    $link = insightLink(['password_hash' => Hash::make('secret')]);

    $this->get('/l/'.$link->slug.'?src=qr')->assertOk()->assertSee('action="'.url('/l/'.$link->slug).'?src=qr"', false);
});

test('every QR code of a short link encodes the ?src=qr form', function () {
    $link = insightLink();
    expect($link->qr_url)->toBe($link->short_url.'?src=qr')
        ->and(ClickSource::tag('https://hel.ink/l/a?x=1'))->toBe('https://hel.ink/l/a?x=1&src=qr');

    // The stored PNG made when the link is created.
    Storage::fake('public');
    app(LinkService::class)->generateQr($link);
    $stored = Storage::disk('public')->get($link->fresh()->qr_code_path);
    expect($stored)->toBe(QrPng::render($link->short_url.'?src=qr', 512, [0, 0, 0], [255, 255, 255], 2))
        ->and($stored)->not->toBe(QrPng::render($link->short_url, 512, [0, 0, 0], [255, 255, 255], 2));

    // The download (SVG is the same code as the PNG, and can be compared exactly).
    $svg = $this->actingAs($link->user)->get(route('links.qr.download', [$link, 'svg']))->assertOk()->getContent();
    $expected = fn (string $data) => (string) QrCode::format('svg')->size(512)->margin(2)->errorCorrection('H')->backgroundColor(255, 255, 255)->color(0, 0, 0)->generate($data);
    expect($svg)->toBe($expected($link->short_url.'?src=qr'))->not->toBe($expected($link->short_url));

    // The preview in the create dialog.
    $this->actingAs($link->user)->get(route('dashboard'))->assertSee('?src=qr', false);
});

test('analytics lists QR scans, direct clicks and in-app browsers', function () {
    $user = User::factory()->create();
    $link = insightLink(['__user' => $user]);
    $go = fn (string $query, string $ua) => $this->withHeader('User-Agent', $ua)->get('/l/'.$link->slug.$query);
    $go('?src=qr', CHROME);
    $go('?src=qr', SAFARI);
    $go('', CHROME);
    $go('', IG);

    $page = $this->actingAs($user)->get(route('analytics'))->assertOk()
        ->assertSee('QR scans vs direct clicks')->assertSee('In-app browsers')->assertSee('QR scans')->assertSee('Instagram');
    expect($page->viewData('insights')['sources'])->toBe(['direct' => 2, 'qr' => 2, 'other' => 0])
        ->and($page->viewData('insights')['in_app'])->toBe(['Instagram' => 1]);
    $this->get(route('links.show', $link))->assertOk()->assertSee('QR scans vs direct clicks');
});

// ---- destination monitor -------------------------------------------------------------------------------------

/** Names count as public addresses (no DNS in tests); IP literals keep their real classification. */
function fakeGuard(): void
{
    app()->instance(UrlGuard::class, new class extends UrlGuard
    {
        protected function resolveHost(string $host): array
        {
            return filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ['93.184.216.34'];
        }
    });
}

function monitored(array $attrs = []): Link
{
    return insightLink($attrs + ['target_url' => 'https://dest.example/page']);
}

function runMonitor(): void
{
    test()->artisan('links:check-destinations')->assertSuccessful();
}

test('the monitor is on the schedule, hourly', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command, 'links:check-destinations'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 * * * *');
});

test('a destination that answers is recorded and not flagged', function () {
    fakeGuard();
    Http::fake(['dest.example/*' => Http::response('', 200)]);
    $link = monitored();

    runMonitor();

    $link->refresh();
    expect($link->last_status)->toBe(200)->and($link->last_checked_at)->not->toBeNull()
        ->and($link->check_failures)->toBe(0)->and($link->isBroken())->toBeFalse();
    Http::assertSent(fn ($r) => $r->method() === 'HEAD' && $r->url() === 'https://dest.example/page');
});

test('a server that dislikes HEAD is asked again with GET', function () {
    fakeGuard();
    Http::fake(fn ($r) => Http::response('', $r->method() === 'HEAD' ? 405 : 200));
    $link = monitored();

    runMonitor();

    expect($link->fresh()->last_status)->toBe(200)->and($link->fresh()->check_failures)->toBe(0);
});

test('a page that refuses robots is not broken', function () {
    fakeGuard();
    Http::fake(['*' => Http::response('', 403)]);
    $link = monitored();

    runMonitor();

    expect($link->fresh()->isBroken())->toBeFalse()->and($link->fresh()->check_failures)->toBe(0);
});

test('a link is flagged only after several failed checks in a row, and the owner is told once', function () {
    fakeGuard();
    Mail::fake();
    Http::fake(['dest.example/*' => Http::response('', 404)]);
    $link = monitored();

    foreach ([1, 2] as $failures) {
        runMonitor();
        expect($link->fresh()->check_failures)->toBe($failures)->and($link->fresh()->isBroken())->toBeFalse();
        $this->travel(7)->hours(); // a failing link is looked at again after the retry period
    }
    Mail::assertNothingQueued();

    runMonitor();
    $link->refresh();
    expect($link->isBroken())->toBeTrue()->and($link->last_status)->toBe(404)->and($link->check_failures)->toBe(3);
    Mail::assertQueued(LinkDestinationBroken::class, 1);
    Mail::assertQueued(LinkDestinationBroken::class, fn ($m) => $m->hasTo($link->user->email) && $m->link->is($link));

    // Still broken on later checks (a flagged link is looked at daily): no second email in the same episode.
    $this->travel(2)->days();
    runMonitor();
    $this->travel(2)->days();
    runMonitor();
    Mail::assertQueued(LinkDestinationBroken::class, 1);
    expect($link->fresh()->isBroken())->toBeTrue();
});

test('a destination that comes back clears the flag, and a new failure is a new episode with a new email', function () {
    fakeGuard();
    Mail::fake();
    $link = monitored();
    $link->forceFill(['check_failures' => 3, 'broken_since' => now()->subDay(), 'last_checked_at' => now()->subDays(2)])->saveQuietly();
    $answer = 200;
    Http::fake(function () use (&$answer) {
        return Http::response('', $answer);
    });

    runMonitor();
    expect($link->fresh()->isBroken())->toBeFalse()->and($link->fresh()->check_failures)->toBe(0);

    $answer = 500;
    foreach (range(1, 3) as $i) {
        $this->travel(2)->days();
        runMonitor();
    }
    expect($link->fresh()->isBroken())->toBeTrue();
    Mail::assertQueued(LinkDestinationBroken::class, 1);
});

test('an unreachable host counts as a failure', function () {
    fakeGuard();
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));
    $link = monitored();

    runMonitor();

    expect($link->fresh()->check_failures)->toBe(1)->and($link->fresh()->last_status)->toBeNull();
});

test('internal addresses are refused and never requested', function (string $url) {
    Http::fake();
    $link = monitored(['target_url' => $url]);

    runMonitor();

    Http::assertNothingSent();
    expect($link->fresh()->check_failures)->toBe(0)->and($link->fresh()->isBroken())->toBeFalse()
        ->and($link->fresh()->last_checked_at)->not->toBeNull();
})->with([
    'loopback' => 'http://127.0.0.1/admin',
    'private' => 'http://10.0.0.5/',
    'private range' => 'http://192.168.1.1:8080/',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'ipv6 loopback' => 'http://[::1]/',
]);

test('a redirect into an internal host is not followed', function () {
    fakeGuard();
    Http::fake([
        'dest.example/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        '*' => Http::response('secret', 200),
    ]);
    $link = monitored();

    runMonitor();

    Http::assertSentCount(1);
    expect($link->fresh()->isBroken())->toBeFalse()->and($link->fresh()->check_failures)->toBe(0);
});

test('redirects are followed by hand up to a cap', function () {
    fakeGuard();
    Http::fake([
        'dest.example/page' => Http::response('', 301, ['Location' => '/moved']),
        'dest.example/moved' => Http::response('', 200),
    ]);
    $link = monitored();
    runMonitor();
    expect($link->fresh()->last_status)->toBe(200);
    Http::assertSentCount(2);

    // A chain that never ends is inconclusive: not counted against the link.
    Http::fake(['dest.example/*' => Http::response('', 302, ['Location' => '/loop'])]);
    $other = monitored();
    runMonitor();
    expect($other->fresh()->check_failures)->toBe(0)->and($other->fresh()->isBroken())->toBeFalse();
});

test('links that are not live, not web addresses, from guests, or from opted-out owners are skipped', function () {
    fakeGuard();
    Http::fake(['*' => Http::response('', 200)]);
    $optedOut = User::factory()->create(['monitor_destinations' => false]);
    $suspended = User::factory()->create(['status' => User::STATUS_SUSPENDED]);
    $skipped = [
        monitored(['status' => 'inactive']),
        monitored(['expires_at' => now()->subMinute()]),
        monitored(['starts_at' => now()->addDay()]),
        monitored(['max_clicks' => 1, 'clicks' => 1]),
        monitored(['target_url' => 'mailto:me@example.com']),
        monitored(['__user' => $optedOut]),
        monitored(['__user' => $suspended]),
        Link::create(['slug' => 'guest1', 'target_url' => 'https://dest.example/g', 'status' => 'active']),
    ];
    $checked = monitored();

    runMonitor();

    foreach ($skipped as $link) {
        expect($link->fresh()->last_checked_at)->toBeNull();
    }
    expect($checked->fresh()->last_checked_at)->not->toBeNull();
});

test('each run checks a bounded number of links, least recently checked first', function () {
    fakeGuard();
    Http::fake(['*' => Http::response('', 200)]);
    $user = User::factory()->create();
    $never = monitored(['__user' => $user, 'target_url' => 'https://a.example/']);
    $old = monitored(['__user' => $user, 'target_url' => 'https://b.example/', 'last_checked_at' => now()->subDays(5)]);
    $older = monitored(['__user' => $user, 'target_url' => 'https://c.example/']);
    $older->forceFill(['last_checked_at' => now()->subDays(9)])->saveQuietly();
    $recent = monitored(['__user' => $user, 'target_url' => 'https://d.example/']);
    $recent->forceFill(['last_checked_at' => now()->subHour()])->saveQuietly();
    $old->forceFill(['last_checked_at' => now()->subDays(5)])->saveQuietly();

    $this->artisan('links:check-destinations', ['--limit' => 2])->assertSuccessful();

    Http::assertSentCount(2);
    expect($never->fresh()->last_checked_at)->not->toBeNull()  // never checked comes first
        ->and($older->fresh()->last_checked_at->isAfter(now()->subMinute()))->toBeTrue()
        ->and($old->fresh()->last_checked_at->isBefore(now()->subDay()))->toBeTrue()
        ->and($recent->fresh()->last_checked_at->isBefore(now()->subMinutes(30)))->toBeTrue()
        ->and($recent->fresh()->last_checked_at->isAfter(now()->subHours(2)))->toBeTrue();
});

test('one run asks a single site only a few times', function () {
    fakeGuard();
    Http::fake(['*' => Http::response('', 200)]);
    $user = User::factory()->create();
    foreach (range(1, 8) as $i) {
        monitored(['__user' => $user, 'target_url' => 'https://dest.example/p'.$i]);
    }

    runMonitor();

    Http::assertSentCount(5);
});

test('changing the destination starts the monitor over', function () {
    $link = monitored();
    $link->forceFill(['check_failures' => 3, 'broken_since' => now(), 'last_status' => 404, 'last_checked_at' => now()])->saveQuietly();

    $link->update(['target_url' => 'https://other.example/fixed']);

    $link->refresh();
    expect($link->isBroken())->toBeFalse()->and($link->check_failures)->toBe(0)
        ->and($link->last_status)->toBeNull()->and($link->last_checked_at)->toBeNull();
});

test('a flagged link shows a chip in the list, the dashboard and the link page', function () {
    $link = monitored();
    $link->forceFill(['broken_since' => now()->subDay(), 'last_status' => 404, 'check_failures' => 3])->saveQuietly();
    $this->actingAs($link->user);

    $this->get(route('links.index'))->assertOk()->assertSee('Destination not responding');
    $this->get(route('dashboard'))->assertOk()->assertSee('Destination not responding');
    $this->get(route('links.show', $link))->assertOk()->assertSee('Destination not responding');
});

test('the owner can switch the monitor off in settings', function () {
    fakeGuard();
    Http::fake(['*' => Http::response('', 200)]);
    $user = User::factory()->create();
    $link = monitored(['__user' => $user]);

    $this->actingAs($user)->get(route('settings'))->assertOk()->assertSee('Check where my links point');
    $this->post(route('settings.monitoring'))->assertRedirect(route('settings'));
    expect($user->fresh()->monitor_destinations)->toBeFalse();

    runMonitor();
    expect($link->fresh()->last_checked_at)->toBeNull();

    $this->post(route('settings.monitoring'), ['monitor_destinations' => '1']);
    expect($user->fresh()->monitor_destinations)->toBeTrue();
});

test('the email names the link and its destination', function () {
    $link = monitored();

    $html = (new LinkDestinationBroken($link, 404))->render();

    expect($html)->toContain($link->short_url)->toContain('dest.example/page')->toContain('HTTP 404')
        ->and((new LinkDestinationBroken($link))->envelope()->subject)->toContain('/l/'.$link->slug);
});

test('the monitor can be switched off for the whole site', function () {
    config(['shortener.monitor.enabled' => false]);
    Http::fake();
    $link = monitored();

    runMonitor();

    Http::assertNothingSent();
    expect($link->fresh()->last_checked_at)->toBeNull();
});
