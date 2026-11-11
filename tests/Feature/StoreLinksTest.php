<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use App\Services\GeoIpService;
use App\Support\StoreLinks;
use Hop\Auth\Models\ApiToken;
use Hop\Safety\LinkSafety;
use Hop\Ui\Locales;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| App store link: one short link, each system to its own store (guided routing rules)
|--------------------------------------------------------------------------
| App ids are invented (com.example.app, id0000000000).
*/

const ST_PLAY = 'https://play.google.com/store/apps/details?id=com.example.app';
const ST_APPLE = 'https://apps.apple.com/us/app/example-app/id0000000000';
const ST_MAC = 'https://apps.apple.com/us/app/example-app/id0000000001';
const ST_MS = 'https://apps.microsoft.com/detail/9abcdef12345';
const ST_FALLBACK = 'https://example.com/get-the-app';

const ST_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1';
const ST_IPAD = 'Mozilla/5.0 (iPad; CPU OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1';
const ST_ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
const ST_MAC_SAFARI = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15';
const ST_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
const ST_LINUX = 'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0';
const ST_BOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

function stForm(array $extra = []): array
{
    return $extra + [
        'target_url' => ST_FALLBACK, 'status' => 'active', 'slug' => 'getapp',
        'store_mode' => true,
        'store_links' => ['play' => ST_PLAY, 'appstore' => ST_APPLE, 'macstore' => ST_MAC, 'msstore' => ST_MS],
    ];
}

function stVisit(string $slug, string $ua)
{
    return test()->withHeaders(['User-Agent' => $ua])->get('/l/'.$slug);
}

beforeEach(function () {
    Http::fake(['*' => Http::response(['security' => []])]);
    test()->mock(GeoIpService::class, function ($m) {
        $m->shouldReceive('country')->andReturn(null);
        $m->shouldReceive('details')->andReturn(['country' => null, 'country_name' => null, 'city' => null, 'region' => null, 'provider' => null]);
    });
});

// --- the accepted addresses ------------------------------------------------------------------------------------

test('each store takes only its own https pages', function (string $store, string $url, bool $ok) {
    expect(StoreLinks::matches($store, $url))->toBe($ok);
})->with([
    // Google Play
    'play: details' => ['play', ST_PLAY, true],
    'play: details with language' => ['play', ST_PLAY.'&hl=de&gl=DE', true],
    'play: developer page' => ['play', 'https://play.google.com/store/apps/dev?id=1234567890123456789', true],
    'play: uppercase host' => ['play', 'https://PLAY.GOOGLE.COM/store/apps/details?id=com.example.app', true],
    'play: http' => ['play', 'http://play.google.com/store/apps/details?id=com.example.app', false],
    'play: no package id' => ['play', 'https://play.google.com/store/apps/details', false],
    'play: a bad package id' => ['play', 'https://play.google.com/store/apps/details?id=bad id', false],
    'play: not the store pages' => ['play', 'https://play.google.com/music/listen', false],
    'play: look-alike host' => ['play', 'https://play.google.com.evil.example/store/apps/details?id=com.example.app', false],
    'play: user info' => ['play', 'https://x@play.google.com/store/apps/details?id=com.example.app', false],
    'play: a port' => ['play', 'https://play.google.com:8443/store/apps/details?id=com.example.app', false],
    'play: the App Store address' => ['play', ST_APPLE, false],
    'play: the Microsoft Store address' => ['play', ST_MS, false],
    // App Store and Mac App Store (the same pages)
    'appstore: with country and name' => ['appstore', ST_APPLE, true],
    'appstore: short form' => ['appstore', 'https://apps.apple.com/app/id0000000000', true],
    'appstore: no name' => ['appstore', 'https://apps.apple.com/us/app/id0000000000', true],
    'appstore: with a query' => ['appstore', ST_APPLE.'?mt=8', true],
    'appstore: itunes host' => ['appstore', 'https://itunes.apple.com/us/app/example-app/id0000000000', true],
    'appstore: http' => ['appstore', 'http://apps.apple.com/app/id0000000000', false],
    'appstore: not an app page' => ['appstore', 'https://apps.apple.com/us/developer/example/id0000000000', false],
    'appstore: no id' => ['appstore', 'https://apps.apple.com/us/app/example-app', false],
    'appstore: look-alike host' => ['appstore', 'https://apps.apple.com.evil.example/app/id0000000000', false],
    'appstore: the Google Play address' => ['appstore', ST_PLAY, false],
    'appstore: the Microsoft Store address' => ['appstore', ST_MS, false],
    'macstore: an App Store page' => ['macstore', ST_MAC, true],
    'macstore: the Google Play address' => ['macstore', ST_PLAY, false],
    // Microsoft Store
    'msstore: apps.microsoft.com detail' => ['msstore', ST_MS, true],
    'msstore: with a locale query' => ['msstore', ST_MS.'?hl=en-us&gl=US', true],
    'msstore: the older store/detail form' => ['msstore', 'https://apps.microsoft.com/store/detail/example-app/9ABCDEF12345', true],
    'msstore: microsoft.com store/apps' => ['msstore', 'https://www.microsoft.com/store/apps/9abcdef12345', true],
    'msstore: microsoft.com productId' => ['msstore', 'https://www.microsoft.com/store/productId/9abcdef12345', true],
    'msstore: microsoft.com p page with a locale' => ['msstore', 'https://www.microsoft.com/en-us/p/example-app/9abcdef12345', true],
    'msstore: microsoft.com store/p page' => ['msstore', 'https://www.microsoft.com/en-us/store/p/example-app/9abcdef12345', true],
    'msstore: http' => ['msstore', 'http://apps.microsoft.com/detail/9abcdef12345', false],
    'msstore: another page of apps.microsoft.com' => ['msstore', 'https://apps.microsoft.com/home', false],
    'msstore: another page of microsoft.com' => ['msstore', 'https://www.microsoft.com/en-us/windows', false],
    'msstore: look-alike host' => ['msstore', 'https://apps.microsoft.com.evil.example/detail/9abcdef12345', false],
    'msstore: the Google Play address' => ['msstore', ST_PLAY, false],
    'msstore: the App Store address' => ['msstore', ST_APPLE, false],
]);

// --- the form --------------------------------------------------------------------------------------------------

test('the form writes one marked routing rule per filled store and keeps the destination as the fallback', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', stForm(['store_links' => ['play' => ST_PLAY, 'msstore' => 'apps.microsoft.com/detail/9abcdef12345', 'appstore' => '']]))->assertOk();
    $link = Link::where('slug', 'getapp')->first();
    expect($link->target_url)->toBe(ST_FALLBACK)
        ->and($link->routing_rules)->toBe([
            ['country' => [], 'language' => [], 'device' => 'any', 'os' => 'android', 'browser' => 'any', 'url' => ST_PLAY, 'store' => 'play'],
            ['country' => [], 'language' => [], 'device' => 'any', 'os' => 'windows', 'browser' => 'any', 'url' => ST_MS, 'store' => 'msstore'],
        ]);
});

test('every system goes to its store, everyone else to the destination', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', stForm())->assertOk();
    foreach ([[ST_ANDROID, ST_PLAY], [ST_IPHONE, ST_APPLE], [ST_IPAD, ST_APPLE], [ST_MAC_SAFARI, ST_MAC], [ST_WINDOWS, ST_MS], [ST_LINUX, ST_FALLBACK], [ST_BOT, ST_FALLBACK]] as [$ua, $to]) {
        stVisit('getapp', $ua)->assertRedirect($to);
    }
});

test('a system without an address goes to the destination', function () {
    $this->actingAs(User::factory()->create())->postJson('/links', stForm(['store_links' => ['play' => ST_PLAY]]))->assertOk();
    stVisit('getapp', ST_ANDROID)->assertRedirect(ST_PLAY);
    foreach ([ST_IPHONE, ST_MAC_SAFARI, ST_WINDOWS] as $ua) {
        stVisit('getapp', $ua)->assertRedirect(ST_FALLBACK);
    }
});

test('the form refuses a wrong store address, naming the store, and an empty form', function (array $links, string $field) {
    $res = $this->actingAs(User::factory()->create())->postJson('/links', stForm(['store_links' => $links]))->assertStatus(422)->assertJsonValidationErrors([$field]);
    expect(Link::count())->toBe(0);
})->with([
    'a web page' => [['play' => 'https://example.com/app'], 'store_links.play'],
    'http' => [['play' => 'http://play.google.com/store/apps/details?id=com.example.app'], 'store_links.play'],
    'the Google Play address in the App Store field' => [['appstore' => ST_PLAY], 'store_links.appstore'],
    'the App Store address in the Google Play field' => [['play' => ST_APPLE], 'store_links.play'],
    'the Microsoft Store address in the Mac field' => [['macstore' => ST_MS], 'store_links.macstore'],
    'the Mac address in the Microsoft field' => [['msstore' => ST_MAC], 'store_links.msstore'],
    'javascript' => [['msstore' => 'javascript:alert(1)'], 'store_links.msstore'],
    'nothing filled in' => [[], 'store_links'],
]);

test('the error names the store, in the visitor language', function () {
    $res = $this->actingAs(User::factory()->create())->postJson('/links?lang=de', stForm(['store_links' => ['appstore' => 'https://example.com/app']]))->assertStatus(422);
    expect($res->json('errors')['store_links.appstore'][0])->toContain('App Store')->toContain('Seite deiner App');
});

test('an address passes the same safety checks as any routing address', function () {
    $this->mock(LinkSafety::class, function ($m) {
        $m->shouldReceive('rejectionReason')->withArgs(fn ($url) => str_starts_with($url, 'https://play.google.com'))->andReturn('This address is not allowed.');
        $m->shouldReceive('rejectionReason')->andReturn(null);
    });
    $this->actingAs(User::factory()->create())->postJson('/links', stForm(['store_links' => ['play' => ST_PLAY]]))->assertStatus(422)->assertJsonValidationErrors(['store_links.play']);
});

// --- edit: the round trip ---------------------------------------------------------------------------------------

test('create, reopen for editing and save unchanged: the same rules come back', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', stForm())->assertOk();
    $link = Link::where('slug', 'getapp')->first();
    $before = $link->routing_rules;
    $edit = $this->actingAs($user)->get('/links/'.$link->id.'/edit')->assertOk();
    $edit->assertSee('data-store-links', false)->assertSee('storeOn: true', false)->assertSee(ST_PLAY, false)->assertSee('rules: []', false);
    // what the edit form submits: the store fields and no plain rules
    $this->actingAs($user)->put('/links/'.$link->id, [
        'target_url' => ST_FALLBACK, 'status' => 'active', 'slug' => 'getapp', 'store_mode' => '1',
        'store_links' => ['play' => ST_PLAY, 'appstore' => ST_APPLE, 'macstore' => ST_MAC, 'msstore' => ST_MS],
    ])->assertRedirect();
    expect($link->fresh()->routing_rules)->toBe($before);
    // turning the form off and saving removes the store rules
    $this->actingAs($user)->put('/links/'.$link->id, ['target_url' => ST_FALLBACK, 'status' => 'active', 'slug' => 'getapp'])->assertRedirect();
    expect($link->fresh()->routing_rules)->toBeNull();
});

test('a link with hand-edited rules opens in the plain rules editor, nothing lost', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', stForm())->assertOk();
    $link = Link::where('slug', 'getapp')->first();
    // the owner adds a country to the Android rule by hand and a rule of their own: no longer what the form writes
    $rules = $link->routing_rules;
    $rules[0]['country'] = ['DE'];
    $rules[] = ['country' => [], 'language' => ['de'], 'device' => 'any', 'os' => 'any', 'browser' => 'any', 'url' => 'https://example.com/de'];
    $this->actingAs($user)->put('/links/'.$link->id, [
        'target_url' => ST_FALLBACK, 'status' => 'active', 'slug' => 'getapp',
        'routing_rules' => array_map(fn ($r) => $r + ['country' => implode(',', $r['country']), 'language' => implode(',', $r['language'])], $rules),
    ])->assertRedirect();
    $saved = $link->fresh()->routing_rules;
    expect($saved)->toHaveCount(5)
        ->and($saved[0])->not->toHaveKey('store')            // the Android rule has a condition the form cannot show
        ->and($saved[1]['store'])->toBe('appstore')           // the others stay what they were
        ->and(StoreLinks::fromRules($saved))->toBeNull();
    $edit = $this->actingAs($user)->get('/links/'.$link->id.'/edit')->assertOk();
    $edit->assertSee('storeOn: false', false)->assertSee(ST_PLAY, false)->assertSee(ST_MS, false);
    expect(str_replace(chr(92), '', $edit->getContent()))->toContain('https://example.com/de');
    // and the redirect still follows every rule
    stVisit('getapp', ST_WINDOWS)->assertRedirect(ST_MS);
});

test('rules written by hand into the exact shape of a store rule are shown in the store form', function () {
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'byhand', 'target_url' => ST_FALLBACK, 'status' => 'active']);
    $this->actingAs($user)->put('/links/'.$link->id, [
        'target_url' => ST_FALLBACK, 'status' => 'active', 'slug' => 'byhand',
        'routing_rules' => [['os' => 'ios', 'url' => ST_APPLE, 'store' => 'appstore'], ['os' => 'android', 'url' => ST_PLAY, 'store' => 'play']],
    ])->assertRedirect();
    expect(StoreLinks::fromRules($link->fresh()->routing_rules))->toBe(['appstore' => ST_APPLE, 'play' => ST_PLAY]);
    // a marker on a rule that does not match is dropped, the rule stays an ordinary one
    $this->actingAs($user)->put('/links/'.$link->id, [
        'target_url' => ST_FALLBACK, 'status' => 'active', 'slug' => 'byhand',
        'routing_rules' => [['os' => 'ios', 'url' => 'https://example.com/ios', 'store' => 'appstore']],
    ])->assertRedirect();
    expect($link->fresh()->routing_rules[0])->not->toHaveKey('store');
});

// --- the other choices -----------------------------------------------------------------------------------------

test('not together with the WhatsApp rotator', function () {
    $this->actingAs(User::factory()->create())->postJson('/links', stForm(['wa_mode' => 'round_robin', 'wa_numbers' => [['number' => '15555550101']]]))
        ->assertStatus(422)->assertJsonValidationErrors(['store_mode']);
    expect(Link::count())->toBe(0);
});

test('with the A/B split the store rule comes first and the split serves everyone else', function () {
    $this->actingAs(User::factory()->create())->postJson('/links', stForm([
        'store_links' => ['play' => ST_PLAY],
        'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://example.com/b', 'weight' => 1]],
    ]))->assertOk();
    stVisit('getapp', ST_ANDROID)->assertRedirect(ST_PLAY);
    expect(LinkClick::first()->variant)->toBeNull();
    $to = stVisit('getapp', ST_LINUX)->assertRedirect()->headers->get('Location');
    expect($to)->toBeIn(['https://example.com/a', 'https://example.com/b']);
});

test('with "Open in the app" the visitor is routed first, then the app of the store page opens', function () {
    $this->actingAs(User::factory()->create())->postJson('/links', stForm([
        'store_links' => ['play' => ST_PLAY, 'appstore' => ST_APPLE],
        'app_links' => ['mode' => 'auto'],
    ]))->assertOk();
    // Android: the Play page, tried in the Play app (market://), the web page as the fallback
    stVisit('getapp', ST_ANDROID)->assertOk()->assertSee('market://details?id=com.example.app', false)->assertSee('Opening Google Play');
    // iPhone: the App Store page, tried in the App Store app
    stVisit('getapp', ST_IPHONE)->assertOk()->assertSee('itms-apps://apps.apple.com/us/app/example-app/id0000000000', false);
    // Windows and Mac: no app opener for those stores, a plain redirect to the routed page
    stVisit('getapp', ST_WINDOWS)->assertRedirect(ST_FALLBACK);
});

// --- the API and who may use it --------------------------------------------------------------------------------

test('the API takes the same rules through routing_rules and keeps the marker', function () {
    $user = User::factory()->create();
    $h = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create', 'link:read'])['plain_token']];
    $res = $this->withHeaders($h)->postJson('/api/shorten', [
        'url' => ST_FALLBACK, 'alias' => 'apistore',
        'routing_rules' => [
            ['os' => 'android', 'store' => 'play', 'url' => ST_PLAY],
            ['os' => 'windows', 'store' => 'msstore', 'url' => ST_MS],
            ['os' => 'ios', 'store' => 'appstore', 'url' => 'https://example.com/not-a-store'],
        ],
    ])->assertCreated();
    $res->assertJsonPath('data.routing_rules.0.store', 'play')->assertJsonPath('data.routing_rules.1.store', 'msstore')->assertJsonPath('data.routing_rules.2.url', 'https://example.com/not-a-store');
    expect(Link::where('slug', 'apistore')->first()->routing_rules[2])->not->toHaveKey('store');
    // the same refusals as every routing rule
    $this->withHeaders($h)->postJson('/api/shorten', ['url' => ST_FALLBACK, 'routing_rules' => [['os' => 'android', 'store' => 'play', 'url' => 'javascript:alert(1)']]])->assertStatus(422);
});

test('guests cannot make one, through the form or the API', function () {
    $this->post('/shorten', ['target_url' => 'https://github.com/navi-crwn', 'cf-turnstile-response' => 'x', 'store_mode' => '1', 'store_links' => ['play' => ST_PLAY]])->assertSessionHasErrors('store_mode');
    $this->post('/shorten', ['target_url' => 'https://github.com/navi-crwn', 'cf-turnstile-response' => 'x', 'routing_rules' => [['os' => 'android', 'url' => ST_PLAY]]])->assertSessionHasErrors('store_mode');
    expect(Link::count())->toBe(0);
    $this->postJson('/api/shorten', ['url' => ST_FALLBACK, 'routing_rules' => [['os' => 'android', 'url' => ST_PLAY]]])->assertStatus(401);
    $this->postJson('/links', stForm())->assertUnauthorized();
    expect(Link::count())->toBe(0);
});

test('the create dialog has the choice, the API docs show an example', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('App store link')->assertSee('data-store-links', false);
    // the dialog's state exists both at the start and after a reset (it is reset every time it opens)
    expect(substr_count($this->actingAs($user)->get('/dashboard')->getContent(), "store_links: { play: '', appstore: '', macstore: '', msstore: '' }"))->toBe(2);
    $this->actingAs($user)->get('/api-docs')->assertOk()->assertSee('App store link: one link, each system to its own store')->assertSee('macstore');
});

test('every new string is translated in all nine languages', function () {
    $keys = json_decode(file_get_contents(base_path('tests/fixtures/k3-store-keys.json')), true);
    $brand = ['Google Play (Android)', 'Mac App Store (macOS)', 'Microsoft Store (Windows)', ':store: :problem'];
    expect($keys)->toHaveCount(14);
    foreach ($keys as $key) {
        foreach (Locales::codes() as $lang) {
            $file = json_decode(file_get_contents(base_path("lang/$lang.json")), true);
            expect($file)->toHaveKey($key);
            if ($lang !== 'en' && ! in_array($key, $brand, true)) {
                expect($file[$key])->not->toBe($key);
            }
        }
    }
});

// --- paths are anchored whole, no dot segments -------------------------------------------------------------------

test('no store takes a path that walks to another page, however the dots are written', function (string $store, string $url) {
    expect(StoreLinks::matches($store, $url))->toBeFalse();
})->with([
    'play: dot dot after the store path' => ['play', 'https://play.google.com/store/apps/x/../../anything'],
    'play: dot dot after details' => ['play', 'https://play.google.com/store/apps/details/../../../account?id=com.example.app'],
    'play: encoded dots' => ['play', 'https://play.google.com/store/apps/%2e%2e/%2e%2e/anything'],
    'play: encoded slash and dots' => ['play', 'https://play.google.com/store/apps/..%2f..%2fanything'],
    'play: twice encoded' => ['play', 'https://play.google.com/store/apps/%252e%252e/anything'],
    'play: trailing text after details' => ['play', 'https://play.google.com/store/apps/details/extra?id=com.example.app'],
    'play: unknown page under store/apps' => ['play', 'https://play.google.com/store/apps/anything'],
    'play: a backslash' => ['play', 'https://play.google.com/store/apps/details\..\x?id=com.example.app'],
    'appstore: dot dot name' => ['appstore', 'https://apps.apple.com/us/app/../id0000000000'],
    'appstore: encoded dot dot' => ['appstore', 'https://apps.apple.com/us/app/%2e%2e/id0000000000'],
    'appstore: a dot in front' => ['appstore', 'https://apps.apple.com/./app/id0000000000'],
    'appstore: more path after the id' => ['appstore', 'https://apps.apple.com/us/app/example/id0000000000/../../x'],
    'macstore: dot dot' => ['macstore', 'https://apps.apple.com/us/app/example/../../us/app/id0000000000'],
    'msstore: dot dot' => ['msstore', 'https://apps.microsoft.com/detail/../anything'],
    'msstore: encoded dot dot' => ['msstore', 'https://apps.microsoft.com/detail/%2E%2E/9abcdef12345'],
    'msstore: microsoft.com dot dot' => ['msstore', 'https://www.microsoft.com/store/apps/../../windows'],
    'msstore: microsoft.com encoded slash' => ['msstore', 'https://www.microsoft.com/en-us/p/example%2f..%2fwindows/9abcdef12345'],
]);

test('the known shapes still pass with the anchored paths', function (string $store, string $url) {
    expect(StoreLinks::matches($store, $url))->toBeTrue();
})->with([
    ['play', 'https://play.google.com/store/apps/details?id=com.example.app'],
    ['play', 'https://play.google.com/store/apps/details/?id=com.example.app'],
    ['play', 'https://play.google.com/store/apps/developer?id=Example+Studio'],
    ['play', 'https://play.google.com/store/apps/collection/topselling_free'],
    ['play', 'https://play.google.com/store/apps/category/GAME'],
    ['appstore', 'https://apps.apple.com/us/app/example-app/id0000000000/'],
    ['appstore', 'https://apps.apple.com/jp/app/%E3%82%A2%E3%83%97%E3%83%AA/id0000000000'],
    ['msstore', 'https://apps.microsoft.com/detail/9abcdef12345/'],
    ['msstore', 'https://apps.microsoft.com/en-us/detail/9abcdef12345'],
    ['msstore', 'https://www.microsoft.com/en-us/p/example-app/9abcdef12345/'],
]);

// --- the A/B split is still checked with the store form on ---------------------------------------------------------

test('a blocked A/B variant is refused with the store form on, through the dashboard form', function () {
    $this->mock(LinkSafety::class, function ($m) {
        $m->shouldReceive('rejectionReason')->withArgs(fn ($url) => str_contains($url, 'blocked.example'))->andReturn('This address is not allowed.');
        $m->shouldReceive('rejectionReason')->andReturn(null);
    });
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', stForm([
        'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://blocked.example/b', 'weight' => 1]],
    ]))->assertStatus(422)->assertJsonValidationErrors(['ab_variants.1.url']);
    expect(Link::count())->toBe(0);
    // and on edit
    $link = Link::create(['user_id' => $user->id, 'slug' => 'ed1', 'target_url' => ST_FALLBACK, 'status' => 'active']);
    $this->actingAs($user)->putJson('/links/'.$link->id, stForm(['slug' => 'ed1', 'ab_variants' => [['url' => 'https://example.com/a'], ['url' => 'https://blocked.example/b']]]))
        ->assertStatus(422)->assertJsonValidationErrors(['ab_variants.1.url']);
    expect($link->fresh()->routing_rules)->toBeNull()->and($link->fresh()->ab_variants)->toBeNull();
});

test('a chained, single or badly weighted A/B variant is refused with the store form on', function (array $variants, string $field) {
    $this->actingAs(User::factory()->create())->postJson('/links', stForm(['ab_variants' => $variants]))->assertStatus(422)->assertJsonValidationErrors([$field]);
    expect(Link::count())->toBe(0);
})->with([
    'a short link of this site' => [[['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://hel.ink/l/abc123', 'weight' => 1]], 'ab_variants.1.url'],
    'only one variant' => [[['url' => 'https://example.com/a', 'weight' => 1]], 'ab_variants'],
    'a weight of zero' => [[['url' => 'https://example.com/a', 'weight' => 0], ['url' => 'https://example.com/b', 'weight' => 1]], 'ab_variants.0.weight'],
]);

test('a blocked A/B variant is refused on the API next to marked store rules', function () {
    $this->mock(LinkSafety::class, function ($m) {
        $m->shouldReceive('rejectionReason')->withArgs(fn ($url) => str_contains($url, 'blocked.example'))->andReturn('This address is not allowed.');
        $m->shouldReceive('rejectionReason')->andReturn(null);
    });
    $user = User::factory()->create();
    $h = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create'])['plain_token']];
    $this->withHeaders($h)->postJson('/api/shorten', [
        'url' => ST_FALLBACK,
        'routing_rules' => [['os' => 'android', 'store' => 'play', 'url' => ST_PLAY]],
        'ab_variants' => [['url' => 'https://example.com/a'], ['url' => 'https://blocked.example/b']],
    ])->assertStatus(422);
    expect(Link::count())->toBe(0);
});
