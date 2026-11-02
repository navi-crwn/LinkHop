<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use App\Services\GeoIpService;
use App\Support\AbSplit;
use App\Support\LinkRouting;
use App\Support\VisitorHash;
use Hop\Auth\Models\ApiToken;
use Hop\Safety\LinkSafety;
use Hop\Ui\Locales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

function rtLink(array $attrs = []): Link
{
    static $n = 0;
    $n++;
    $user = $attrs['__user'] ?? User::factory()->create();
    unset($attrs['__user']);

    return Link::create($attrs + ['user_id' => $user->id, 'slug' => 'route'.$n, 'target_url' => 'https://example.com/default'.$n, 'status' => 'active']);
}

function rtRule(array $attrs): array
{
    return $attrs + ['country' => [], 'language' => [], 'device' => 'any', 'os' => 'any', 'browser' => 'any'];
}

const RT_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
const RT_WINDOWS_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
const RT_ANDROID_CHROME = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
const RT_WINDOWS_FIREFOX = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0';
const RT_SEARCH_BOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

function rtVisit(string $slug, string $ua, array $headers = [], string $ip = '203.0.113.7')
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders($headers + ['User-Agent' => $ua])->get('/l/'.$slug);
}

// The click log looks up country and proxy status; keep the tests off the network.
beforeEach(function () {
    Http::fake(['*' => Http::response(['security' => []])]);
    rtCountry(null);
});

function rtCountry(?string $code): void
{
    test()->mock(GeoIpService::class, function ($m) use ($code) {
        $m->shouldReceive('country')->andReturn($code);
        $m->shouldReceive('details')->andReturn(['country' => $code, 'country_name' => null, 'city' => null, 'region' => null, 'provider' => null]);
    });
}

test('a link without rules or a split goes to its own address, as before', function () {
    $link = rtLink();
    rtVisit($link->slug, RT_IPHONE)->assertRedirect($link->target_url);
    expect(LinkClick::first()->variant)->toBeNull();
});

test('the first matching rule wins, and the link address is the default', function () {
    $link = rtLink(['routing_rules' => [
        rtRule(['device' => 'mobile', 'os' => 'ios', 'url' => 'https://apps.example.com/ios']),
        rtRule(['device' => 'mobile', 'url' => 'https://m.example.com/']),
        rtRule(['browser' => 'firefox', 'url' => 'https://example.com/firefox']),
    ]]);
    rtVisit($link->slug, RT_IPHONE)->assertRedirect('https://apps.example.com/ios');
    rtVisit($link->slug, RT_ANDROID_CHROME)->assertRedirect('https://m.example.com/');
    rtVisit($link->slug, RT_WINDOWS_FIREFOX)->assertRedirect('https://example.com/firefox');
    rtVisit($link->slug, RT_WINDOWS_CHROME)->assertRedirect($link->target_url);
});

test('system and browser rules read the names the user agent parser gives', function () {
    expect(LinkRouting::systemOf('iOS'))->toBe('ios')->and(LinkRouting::systemOf('iPadOS'))->toBe('ios')
        ->and(LinkRouting::systemOf('Android'))->toBe('android')->and(LinkRouting::systemOf('Windows'))->toBe('windows')
        ->and(LinkRouting::systemOf('Mac'))->toBe('macos')->and(LinkRouting::systemOf('GNU/Linux'))->toBe('linux')
        ->and(LinkRouting::systemOf('Ubuntu'))->toBe('linux')->and(LinkRouting::systemOf('Chrome OS'))->toBe('chromeos')
        ->and(LinkRouting::systemOf('Unknown'))->toBeNull();
    expect(LinkRouting::browserOf('Chrome Mobile'))->toBe('chrome')->and(LinkRouting::browserOf('Mobile Safari'))->toBe('safari')
        ->and(LinkRouting::browserOf('Microsoft Edge'))->toBe('edge')->and(LinkRouting::browserOf('Samsung Browser'))->toBe('samsung')
        ->and(LinkRouting::browserOf('Firefox'))->toBe('firefox')->and(LinkRouting::browserOf('Opera Mobile'))->toBe('opera')
        ->and(LinkRouting::browserOf('Brave Tor'))->toBeNull();
    $link = rtLink(['routing_rules' => [rtRule(['os' => 'windows', 'browser' => 'chrome', 'url' => 'https://example.com/win-chrome'])]]);
    rtVisit($link->slug, RT_WINDOWS_CHROME)->assertRedirect('https://example.com/win-chrome');
    rtVisit($link->slug, RT_WINDOWS_FIREFOX)->assertRedirect($link->target_url);
});

test('all conditions of a rule have to hold', function () {
    $link = rtLink(['routing_rules' => [rtRule(['device' => 'desktop', 'os' => 'windows', 'language' => ['de'], 'url' => 'https://example.com/de-windows'])]]);
    rtVisit($link->slug, RT_WINDOWS_CHROME, ['Accept-Language' => 'de-DE,de;q=0.9'])->assertRedirect('https://example.com/de-windows');
    rtVisit($link->slug, RT_WINDOWS_CHROME, ['Accept-Language' => 'fr-FR'])->assertRedirect($link->target_url);
    rtVisit($link->slug, RT_IPHONE, ['Accept-Language' => 'de-DE'])->assertRedirect($link->target_url);
});

test('a language rule follows the visitor’s favourite language, and a region only narrows it when asked', function () {
    expect(LinkRouting::languages('en-US,en;q=0.9,de;q=0.95,fr;q=0'))->toBe(['en-us', 'de', 'en'])
        ->and(LinkRouting::languages('*'))->toBe([])->and(LinkRouting::languages(null))->toBe([]);
    $link = rtLink(['routing_rules' => [
        rtRule(['language' => ['pt-br'], 'url' => 'https://example.com/brasil']),
        rtRule(['language' => ['pt'], 'url' => 'https://example.com/portugues']),
    ]]);
    rtVisit($link->slug, RT_WINDOWS_CHROME, ['Accept-Language' => 'pt-BR,pt;q=0.9'])->assertRedirect('https://example.com/brasil');
    rtVisit($link->slug, RT_WINDOWS_CHROME, ['Accept-Language' => 'pt-PT,en;q=0.5'])->assertRedirect('https://example.com/portugues');
    // English first, Portuguese second: not a Portuguese visitor
    rtVisit($link->slug, RT_WINDOWS_CHROME, ['Accept-Language' => 'en;q=0.9,pt;q=0.5'])->assertRedirect($link->target_url);
    rtVisit($link->slug, RT_WINDOWS_CHROME)->assertRedirect($link->target_url);
});

test('a country rule uses the GeoIP country, and only looks it up when a rule needs it', function () {
    rtCountry('DE');
    $link = rtLink(['routing_rules' => [
        rtRule(['country' => ['FR', 'DE'], 'url' => 'https://example.com/dach-fr']),
    ]]);
    rtVisit($link->slug, RT_WINDOWS_CHROME)->assertRedirect('https://example.com/dach-fr');

    rtCountry('US');
    rtVisit($link->slug, RT_WINDOWS_CHROME)->assertRedirect($link->target_url);

    rtCountry(null);
    rtVisit($link->slug, RT_WINDOWS_CHROME)->assertRedirect($link->target_url);
});

test('the country is not looked up when the cheaper conditions already fail, or no rule has a country', function () {
    $this->mock(GeoIpService::class, function ($m) {
        $m->shouldReceive('country')->never();
        $m->shouldReceive('details')->andReturn(['country' => null, 'country_name' => null, 'city' => null, 'region' => null, 'provider' => null]);
    });
    $link = rtLink(['routing_rules' => [
        rtRule(['device' => 'tablet', 'country' => ['DE'], 'url' => 'https://example.com/tablet-de']),
        rtRule(['browser' => 'firefox', 'url' => 'https://example.com/ff']),
    ]]);
    rtVisit($link->slug, RT_WINDOWS_CHROME)->assertRedirect($link->target_url);
    rtVisit($link->slug, RT_WINDOWS_FIREFOX)->assertRedirect('https://example.com/ff');
});

test('a rule is cheap: loading the link and logging the click are the only queries it adds', function () {
    $link = rtLink(['routing_rules' => [rtRule(['device' => 'mobile', 'url' => 'https://m.example.com/'])]]);
    rtVisit($link->slug, RT_WINDOWS_CHROME); // warms the link cache
    $without = rtLink();
    rtVisit($without->slug, RT_WINDOWS_CHROME);
    DB::enableQueryLog();
    rtVisit($link->slug, RT_IPHONE)->assertRedirect('https://m.example.com/');
    $withRules = count(DB::getQueryLog());
    DB::flushQueryLog();
    rtVisit($without->slug, RT_IPHONE);
    $plain = count(DB::getQueryLog());
    expect($withRules)->toBe($plain);
});

test('crawlers and preview bots always get the default address, never a rule or a variant', function () {
    $link = rtLink([
        'routing_rules' => [rtRule(['browser' => 'chrome', 'url' => 'https://example.com/rule'])],
        'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://example.com/b', 'weight' => 1]],
    ]);
    foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4'] as $ip) {
        rtVisit($link->slug, RT_SEARCH_BOT, ['Accept-Language' => 'de'], $ip)->assertRedirect($link->target_url);
    }
    expect(LinkClick::count())->toBe(0);
});

test('a rule that matches beats the split, a rule that does not leaves the split in charge', function () {
    $link = rtLink([
        'routing_rules' => [rtRule(['device' => 'mobile', 'url' => 'https://example.com/mobile'])],
        'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://example.com/b', 'weight' => 1]],
    ]);
    foreach (['198.51.100.1', '198.51.100.2', '198.51.100.3', '198.51.100.4', '198.51.100.5'] as $ip) {
        rtVisit($link->slug, RT_IPHONE, [], $ip)->assertRedirect('https://example.com/mobile');
    }
    expect(LinkClick::whereNotNull('variant')->count())->toBe(0);
    $desktop = rtVisit($link->slug, RT_WINDOWS_CHROME, [], '198.51.100.9');
    expect($desktop->headers->get('Location'))->toBeIn(['https://example.com/a', 'https://example.com/b']);
    expect(LinkClick::whereNotNull('variant')->count())->toBe(1);
});

test('a returning visitor sees the same variant, and the click records which one', function () {
    $link = rtLink(['ab_variants' => [
        ['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://example.com/b', 'weight' => 1], ['url' => 'https://example.com/c', 'weight' => 1],
    ]]);
    $first = rtVisit($link->slug, RT_WINDOWS_CHROME, [], '192.0.2.44')->headers->get('Location');
    foreach (range(1, 6) as $_) {
        rtVisit($link->slug, RT_WINDOWS_CHROME, [], '192.0.2.44')->assertRedirect($first);
    }
    $position = ['https://example.com/a' => 1, 'https://example.com/b' => 2, 'https://example.com/c' => 3][$first];
    expect(LinkClick::pluck('variant')->unique()->all())->toBe([$position]);
});

test('the pick follows the weights over many visitors', function () {
    $variants = [['url' => 'https://example.com/a', 'weight' => 70], ['url' => 'https://example.com/b', 'weight' => 20], ['url' => 'https://example.com/c', 'weight' => 10]];
    $counts = [0, 0, 0];
    for ($i = 0; $i < 4000; $i++) {
        $counts[AbSplit::pick($variants, 7, VisitorHash::make('10.1.'.intdiv($i, 250).'.'.($i % 250 + 1)))]++;
    }
    expect($counts[0])->toBeBetween(2650, 2950)->and($counts[1])->toBeBetween(680, 920)->and($counts[2])->toBeBetween(320, 480);
});

test('the same visitor can land on different variants on different links, and with no address the pick is random but valid', function () {
    $variants = [['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://example.com/b', 'weight' => 1]];
    $hash = VisitorHash::make('203.0.113.5');
    $seen = collect(range(1, 40))->map(fn ($id) => AbSplit::pick($variants, $id, $hash))->unique()->all();
    expect($seen)->toHaveCount(2);
    foreach (range(1, 20) as $_) {
        expect(AbSplit::pick($variants, 1, null))->toBeIn([0, 1]);
    }
});

test('a visitor hash is a keyed hash, not the address', function () {
    $link = rtLink(['ab_variants' => [['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://example.com/b', 'weight' => 1]]]);
    rtVisit($link->slug, RT_WINDOWS_CHROME, [], '192.0.2.99');
    expect(LinkClick::first()->getRawOriginal('ip_address'))->not->toContain('192.0.2.99');
});

test('the edit page and the analytics show clicks per variant', function () {
    $user = User::factory()->create();
    $link = rtLink(['__user' => $user, 'slug' => 'splitshow', 'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 3], ['url' => 'https://example.com/b', 'weight' => 1]]]);
    LinkClick::create(['link_id' => $link->id, 'user_agent' => 'x', 'clicked_at' => now(), 'variant' => 1]);
    LinkClick::create(['link_id' => $link->id, 'user_agent' => 'x', 'clicked_at' => now(), 'variant' => 1]);
    LinkClick::create(['link_id' => $link->id, 'user_agent' => 'x', 'clicked_at' => now(), 'variant' => 2]);
    expect(AbSplit::clicks($link))->toBe([1 => 2, 2 => 1]);
    $this->actingAs($user)->get('/links/'.$link->id)->assertOk()->assertSee('Split test')->assertSee('https://example.com/a')->assertSee('75%');
    $this->actingAs($user)->get('/links/'.$link->id.'/edit')->assertOk()->assertSee('Split test');
    $plain = rtLink(['__user' => $user, 'slug' => 'nosplitshow']);
    $this->actingAs($user)->get('/links/'.$plain->id)->assertOk()->assertDontSee('Split test');
});

test('the form saves rules and variants, tidies them, and keeps them on the link', function () {
    $user = User::factory()->create();
    $res = $this->actingAs($user)->postJson('/links', [
        'target_url' => 'https://example.com/main', 'status' => 'active', 'slug' => 'routed1',
        'routing_rules' => [
            ['country' => 'de, at', 'language' => 'DE', 'device' => 'mobile', 'os' => 'any', 'browser' => '', 'url' => 'example.com/de'],
            ['country' => '', 'language' => '', 'device' => 'any', 'os' => 'any', 'browser' => 'any', 'url' => ''],
        ],
        'ab_variants' => [['url' => 'https://example.com/a', 'weight' => '30'], ['url' => 'https://example.com/b', 'weight' => '70'], ['url' => '', 'weight' => '50']],
    ])->assertOk();
    $link = Link::where('slug', 'routed1')->first();
    expect($link->routing_rules)->toBe([['country' => ['DE', 'AT'], 'language' => ['de'], 'device' => 'mobile', 'os' => 'any', 'browser' => 'any', 'url' => 'https://example.com/de']])
        ->and($link->ab_variants)->toBe([['url' => 'https://example.com/a', 'weight' => 30], ['url' => 'https://example.com/b', 'weight' => 70]]);

    $this->actingAs($user)->put('/links/'.$link->id, ['target_url' => 'https://example.com/main', 'status' => 'active', 'slug' => 'routed1'])->assertRedirect();
    expect($link->fresh()->routing_rules)->toBeNull()->and($link->fresh()->ab_variants)->toBeNull();
});

test('the form refuses bad rules and variants', function () {
    $user = User::factory()->create();
    $post = fn (array $extra, string $n) => $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/v'.$n, 'status' => 'active'] + $extra);
    $post(['routing_rules' => [rtRule(['url' => 'https://example.com/x'])]], '1')->assertStatus(422)->assertJsonValidationErrors('routing_rules.0');
    $post(['routing_rules' => [rtRule(['country' => ['GERMANY'], 'url' => 'https://example.com/x'])]], '2')->assertStatus(422)->assertJsonValidationErrors('routing_rules.0.country');
    $post(['routing_rules' => [rtRule(['language' => ['english-us-x'], 'url' => 'https://example.com/x'])]], '3')->assertStatus(422)->assertJsonValidationErrors('routing_rules.0.language');
    $post(['routing_rules' => [rtRule(['device' => 'fridge', 'url' => 'https://example.com/x'])]], '4')->assertStatus(422)->assertJsonValidationErrors('routing_rules.0.device');
    $post(['routing_rules' => [rtRule(['device' => 'mobile', 'url' => ''])], 'ab_variants' => []], '5')->assertStatus(422);
    $post(['routing_rules' => [rtRule(['device' => 'mobile', 'url' => 'ftp://example.com/x'])]], '6')->assertStatus(422)->assertJsonValidationErrors('routing_rules.0.url');
    $post(['routing_rules' => array_fill(0, 21, rtRule(['device' => 'mobile', 'url' => 'https://example.com/x']))], '7')->assertStatus(422)->assertJsonValidationErrors('routing_rules');
    $post(['routing_rules' => 'everything'], '8')->assertStatus(422)->assertJsonValidationErrors('routing_rules');

    $post(['ab_variants' => [['url' => 'https://example.com/a', 'weight' => 1]]], '9')->assertStatus(422)->assertJsonValidationErrors('ab_variants');
    $post(['ab_variants' => array_fill(0, 6, ['url' => 'https://example.com/a', 'weight' => 1])], '10')->assertStatus(422)->assertJsonValidationErrors('ab_variants');
    $post(['ab_variants' => [['url' => 'https://example.com/a', 'weight' => 0], ['url' => 'https://example.com/b', 'weight' => 1]]], '11')->assertStatus(422)->assertJsonValidationErrors('ab_variants.0.weight');
    $post(['ab_variants' => [['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://example.com/b', 'weight' => 'lots']]], '12')->assertStatus(422)->assertJsonValidationErrors('ab_variants.1.weight');
    $post(['ab_variants' => [['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'javascript:alert(1)', 'weight' => 1]]], '13')->assertStatus(422)->assertJsonValidationErrors('ab_variants.1.url');
    expect(Link::count())->toBe(0);
});

test('twenty rules and five variants are the most a link can have, and fit', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', [
        'target_url' => 'https://example.com/max', 'status' => 'active', 'slug' => 'maxed',
        'routing_rules' => array_map(fn ($i) => rtRule(['country' => ['DE'], 'url' => 'https://example.com/r'.$i]), range(1, 20)),
        'ab_variants' => array_map(fn ($i) => ['url' => 'https://example.com/v'.$i, 'weight' => $i], range(1, 5)),
    ])->assertOk();
    expect(Link::where('slug', 'maxed')->first())->routing_rules->toHaveCount(20)->ab_variants->toHaveCount(5);
});

test('destinations in rules and variants go through the same safety checks as the main address', function () {
    config(['shortener.public_url' => 'https://hel.ink']);
    $user = User::factory()->create();
    // an address the safety checks refuse for the main URL is refused here too
    $bad = collect(['http://127.0.0.1/admin', 'https://203.0.113.9/', 'javascript:alert(1)', 'data:text/html,hi'])
        ->first(fn ($u) => app(LinkSafety::class)->rejectionReason($u) !== null);
    expect($bad)->not->toBeNull();
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/s1', 'status' => 'active', 'routing_rules' => [rtRule(['device' => 'mobile', 'url' => $bad])]])
        ->assertStatus(422)->assertJsonValidationErrors('routing_rules.0.url');
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/s2', 'status' => 'active', 'ab_variants' => [['url' => 'https://example.com/ok', 'weight' => 1], ['url' => $bad, 'weight' => 1]]])
        ->assertStatus(422)->assertJsonValidationErrors('ab_variants.1.url');
    // and a short link of this site would loop
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/s3', 'status' => 'active', 'routing_rules' => [rtRule(['device' => 'mobile', 'url' => 'https://hel.ink/l/other'])]])
        ->assertStatus(422)->assertJsonValidationErrors('routing_rules.0.url');
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/s4', 'status' => 'active', 'ab_variants' => [['url' => 'https://HEL.ink/L/other', 'weight' => 1], ['url' => 'https://example.com/b', 'weight' => 1]]])
        ->assertStatus(422)->assertJsonValidationErrors('ab_variants.0.url');
});

test('the safety check also holds when a link is edited', function () {
    $user = User::factory()->create();
    $link = rtLink(['__user' => $user, 'slug' => 'editsafe']);
    $this->actingAs($user)->put('/links/'.$link->id, ['target_url' => $link->target_url, 'status' => 'active', 'slug' => 'editsafe', 'routing_rules' => [rtRule(['device' => 'mobile', 'url' => 'javascript:alert(1)'])]])
        ->assertSessionHasErrors('routing_rules.0.url');
    expect($link->fresh()->routing_rules)->toBeNull();
});

test('the API takes and returns rules and variants', function () {
    $user = User::factory()->create();
    $h = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create', 'link:read'])['plain_token']];
    $res = $this->withHeaders($h)->postJson('/api/shorten', [
        'url' => 'https://example.com/api-route', 'alias' => 'apiroute',
        'routing_rules' => [['country' => ['DE'], 'device' => 'mobile', 'url' => 'https://example.com/de-mobile']],
        'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://example.com/b']],
    ])->assertCreated();
    $res->assertJsonPath('data.routing_rules.0.country.0', 'DE')->assertJsonPath('data.routing_rules.0.url', 'https://example.com/de-mobile')
        ->assertJsonPath('data.ab_variants.1.weight', 1);
    $this->withHeaders($h)->getJson('/api/links/apiroute')->assertOk()->assertJsonPath('data.ab_variants.0.url', 'https://example.com/a');
    $plain = $this->withHeaders($h)->postJson('/api/shorten', ['url' => 'https://example.com/api-plain'])->assertCreated();
    $plain->assertJsonPath('data.routing_rules', [])->assertJsonPath('data.ab_variants', []);
});

test('the API refuses the same things the form does', function () {
    $user = User::factory()->create();
    $h = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create'])['plain_token']];
    $this->withHeaders($h)->postJson('/api/shorten', ['url' => 'https://example.com/x1', 'ab_variants' => [['url' => 'https://example.com/a']]])->assertStatus(422)->assertJsonPath('messages.ab_variants.0', fn ($m) => is_string($m));
    $this->withHeaders($h)->postJson('/api/shorten', ['url' => 'https://example.com/x2', 'routing_rules' => [['url' => 'https://example.com/a']]])->assertStatus(422);
    $this->withHeaders($h)->postJson('/api/shorten', ['url' => 'https://example.com/x3', 'routing_rules' => [['device' => 'mobile', 'url' => 'javascript:alert(1)']]])->assertStatus(422);
    $this->withHeaders($h)->postJson('/api/shorten', ['url' => 'https://example.com/x4', 'routing_rules' => 'nope'])->assertStatus(422);
    expect(Link::count())->toBe(0);
});

test('the edit page shows the routing and split fields with what is saved', function () {
    $user = User::factory()->create();
    $link = rtLink(['__user' => $user, 'slug' => 'editroute',
        'routing_rules' => [rtRule(['country' => ['DE'], 'device' => 'mobile', 'url' => 'https://example.com/de-m'])],
        'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 60], ['url' => 'https://example.com/b', 'weight' => 40]]]);
    $this->actingAs($user)->get('/links/'.$link->id.'/edit')->assertOk()
        ->assertSee('Routing rules')->assertSee('A/B split')->assertSee('/de-m\\u0022', false)->assertSee('country\\u0022:\\u0022DE', false)->assertSee('https://example.com/b')
        ->assertSee('routing_rules[', false)->assertSee('ab_variants[', false)->assertSee('Campaign tags');
});

test('the list marks links that route or split', function () {
    $user = User::factory()->create();
    rtLink(['__user' => $user, 'slug' => 'chiprules', 'routing_rules' => [rtRule(['device' => 'mobile', 'url' => 'https://example.com/m']), rtRule(['os' => 'ios', 'url' => 'https://example.com/i']), rtRule(['os' => 'android', 'url' => 'https://example.com/a'])]]);
    rtLink(['__user' => $user, 'slug' => 'chipsplit', 'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://example.com/b', 'weight' => 1]]]);
    $this->actingAs($user)->get('/links')->assertOk()->assertSee('3 rules')->assertSee('A/B');
});

test('the API docs describe the new fields', function () {
    $this->actingAs(User::factory()->create())->get('/api-docs')->assertOk()->assertSee('routing_rules')->assertSee('ab_variants');
});

test('the create modal carries the routing and campaign tag helpers', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Campaign tags')->assertSee('utm_source', false)->assertSee('Routing rules');
});

test('the campaign tag builder is a component with its presets in code and nothing stored', function () {
    $user = User::factory()->create();
    $link = rtLink(['__user' => $user, 'slug' => 'utmedit']);
    $html = $this->actingAs($user)->get('/links/'.$link->id.'/edit')->assertOk()->getContent();
    expect($html)->toContain('Campaign tags (UTM)')->toContain('Newsletter')->toContain('LinkedIn')->toContain('utm_campaign')
        ->and($html)->toContain('document.getElementById(this.target)');
    // the builder only rewrites the address field, so the saved address is the only thing that reaches the server
    $this->actingAs($user)->put('/links/'.$link->id, ['target_url' => 'https://example.com/p?utm_source=newsletter&utm_medium=email&utm_campaign=spring', 'status' => 'active', 'slug' => 'utmedit'])->assertRedirect();
    expect($link->fresh()->target_url)->toBe('https://example.com/p?utm_source=newsletter&utm_medium=email&utm_campaign=spring');
    expect(Schema::hasColumn('links', 'utm_source'))->toBeFalse();
});

test('every new string is translated in all nine languages', function () {
    $en = json_decode(file_get_contents(base_path('lang/en.json')), true);
    foreach (['Routing rules', 'A/B split', 'Campaign tags (UTM)', 'Add a rule', 'Add a variant', 'Split test results', 'Rule :n is not valid.'] as $key) {
        foreach (Locales::codes() as $lang) {
            $file = json_decode(file_get_contents(base_path("lang/$lang.json")), true);
            expect($file)->toHaveKey($key);
            if ($lang !== 'en') {
                expect($file[$key])->not->toBe($key);
            }
        }
    }
    expect($en)->toHaveKey('Weight :share');
});
