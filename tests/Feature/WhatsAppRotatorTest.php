<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use App\Services\GeoIpService;
use App\Support\WhatsAppRotator;
use Hop\Safety\LinkSafety;
use Hop\Ui\Locales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| WhatsApp rotator (accounts only). Numbers are the 555 range: invented, never real.
|--------------------------------------------------------------------------
*/

const WA_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
const WA_BOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

function waNumbers(array $weights = [1, 1, 1]): array
{
    $out = [];
    foreach ($weights as $i => $w) {
        $out[] = ['id' => $i + 1, 'number' => '1555555010'.($i + 1), 'weight' => $w, 'message' => $i === 0 ? 'Hi & welcome: 50% off?' : ''];
    }

    return $out;
}

function waLink(string $mode = 'round_robin', array $weights = [1, 1, 1], array $attrs = []): Link
{
    static $n = 0;
    $n++;
    $numbers = waNumbers($weights);

    return Link::create($attrs + [
        'user_id' => User::factory()->create()->id, 'slug' => 'wa'.$n, 'status' => 'active',
        'target_url' => WhatsAppRotator::url($numbers[0]['number']), 'wa_mode' => $mode, 'wa_numbers' => $numbers,
    ]);
}

function waVisit(string $slug, string $ua = WA_CHROME)
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

// --- schema ----------------------------------------------------------------------------------------------------

test('the rotator migration adds its columns and takes them away again', function () {
    $migration = require base_path('database/migrations/2026_11_13_000001_add_whatsapp_rotator_to_links_table.php');
    expect(Schema::hasColumns('links', ['wa_mode', 'wa_numbers', 'wa_cursor']))->toBeTrue();
    $migration->down();
    expect(Schema::hasColumn('links', 'wa_mode'))->toBeFalse()
        ->and(Schema::hasColumn('links', 'wa_numbers'))->toBeFalse()
        ->and(Schema::hasColumn('links', 'wa_cursor'))->toBeFalse();
    $migration->up();
    $migration->up(); // running it again is harmless
    expect(Schema::hasColumns('links', ['wa_mode', 'wa_numbers', 'wa_cursor']))->toBeTrue();
});

// --- numbers ---------------------------------------------------------------------------------------------------

test('numbers are stored as digits with the country code, anything else is refused', function (mixed $input, ?string $expected) {
    expect(WhatsAppRotator::normalize($input))->toBe($expected);
})->with([
    'plain digits' => ['15555550101', '15555550101'],
    'plus sign' => ['+15555550101', '15555550101'],
    'spaces, dots, dashes, brackets' => ['+1 (555) 555-0101', '15555550101'],
    'double zero' => ['0015555550101', '15555550101'],
    'shortest, 8 digits' => ['12345678', '12345678'],
    'longest, 15 digits' => ['123456789012345', '123456789012345'],
    'too short, 7 digits' => ['1234567', null],
    'too long, 16 digits' => ['1234567890123456', null],
    'national format with a leading zero' => ['0555550101', null],
    'letters' => ['1555CALLME', null],
    'a url' => ['https://wa.me/15555550101', null],
    'plus in the middle' => ['1555+5550101', null],
    'empty' => ['', null],
    'an array' => [['15555550101'], null],
]);

// --- picking: deterministic ------------------------------------------------------------------------------------

test('round robin walks the list and starts over', function () {
    $n = waNumbers([1, 1, 1]);
    $picked = array_map(fn ($turn) => WhatsAppRotator::slot($n, 'round_robin', $turn), range(0, 8));
    expect($picked)->toBe([0, 1, 2, 0, 1, 2, 0, 1, 2]);
});

test('weighted turns give every number exactly its share over each cycle', function () {
    $n = waNumbers([3, 1, 2]);
    $cycle = array_map(fn ($turn) => WhatsAppRotator::slot($n, 'weighted', $turn), range(0, 5));
    expect(array_count_values($cycle))->toBe([0 => 3, 1 => 1, 2 => 2]);
    // ten whole cycles: still exact, nothing drifts
    $many = array_count_values(array_map(fn ($turn) => WhatsAppRotator::slot($n, 'weighted', $turn), range(0, 59)));
    expect($many)->toBe([0 => 30, 1 => 10, 2 => 20]);
});

test('one number always gets the visit', function () {
    expect(WhatsAppRotator::slot(waNumbers([5]), 'weighted', 7))->toBe(0)
        ->and(WhatsAppRotator::slot(waNumbers([1]), 'round_robin', 7))->toBe(0);
});

// --- the redirect ----------------------------------------------------------------------------------------------

test('visits are sent to wa.me, in turn, with the message encoded', function () {
    $link = waLink('round_robin');
    $urls = [];
    foreach (range(1, 6) as $_) {
        $urls[] = waVisit($link->slug)->assertRedirect()->headers->get('Location');
    }
    expect($urls)->toBe([
        'https://wa.me/15555550101?text=Hi%20%26%20welcome%3A%2050%25%20off%3F', 'https://wa.me/15555550102', 'https://wa.me/15555550103',
        'https://wa.me/15555550101?text=Hi%20%26%20welcome%3A%2050%25%20off%3F', 'https://wa.me/15555550102', 'https://wa.me/15555550103',
    ]);
    expect(Link::find($link->id)->wa_cursor)->toBe(6);
});

test('a weighted link shares the visits by weight and counts them per number', function () {
    $link = waLink('weighted', [3, 1]);
    foreach (range(1, 8) as $_) {
        waVisit($link->slug)->assertRedirect();
    }
    expect(WhatsAppRotator::clicks($link->fresh()))->toBe([1 => 6, 2 => 2]);
    expect(Link::find($link->id)->clicks)->toBe(8); // the ordinary click count keeps working
});

test('two stale copies of the link still take different turns: the counter lives in the database', function () {
    $link = waLink('round_robin', [1, 1]);
    $a = Link::find($link->id);
    $b = Link::find($link->id); // both read wa_cursor = 0, as two requests with a cached link would
    $first = WhatsAppRotator::pick($a, true)['id'];
    $second = WhatsAppRotator::pick($b, true)['id'];
    expect([$first, $second])->toBe([1, 2]);
});

test('crawlers get the first number and do not use up a turn or count', function () {
    $link = waLink('round_robin');
    waVisit($link->slug, WA_BOT)->assertRedirect(WhatsAppRotator::url('15555550101', 'Hi & welcome: 50% off?'));
    expect(Link::find($link->id)->wa_cursor)->toBe(0)->and(LinkClick::count())->toBe(0);
});

test('an ordinary link pays nothing for the rotator, a rotator link pays one UPDATE and one SELECT', function () {
    $this->travelTo(now()->startOfSecond());
    $plain = Link::create(['user_id' => User::factory()->create()->id, 'slug' => 'plainone', 'target_url' => 'https://example.com/plain', 'status' => 'active']);
    $rotator = waLink('round_robin');
    waVisit($plain->slug); // warm the link cache
    waVisit($rotator->slug);
    $this->travelTo(now()->addSecond());
    DB::enableQueryLog();
    waVisit($plain->slug)->assertRedirect('https://example.com/plain');
    $plainQueries = array_column(DB::getQueryLog(), 'query');
    DB::flushQueryLog();
    waVisit($rotator->slug)->assertRedirect();
    $rotatorQueries = array_column(DB::getQueryLog(), 'query');
    $this->travelBack();
    expect(array_filter($plainQueries, fn ($q) => str_contains($q, 'wa_')))->toBe([])
        ->and(count($rotatorQueries) - count($plainQueries))->toBe(2)
        ->and(count(array_filter($rotatorQueries, fn ($q) => str_contains($q, 'wa_cursor'))))->toBe(2);
});

test('a rotator answers with a temporary redirect that browsers do not keep', function () {
    $link = waLink('round_robin', [1, 1], ['redirect_type' => '301']);
    waVisit($link->slug)->assertStatus(302)->assertHeader('Cache-Control', 'no-store, private');
});

// --- the form --------------------------------------------------------------------------------------------------

test('the form saves a rotator, normalises the numbers, ignores the destination and drops the other routing', function () {
    $this->travelTo(now()->startOfSecond());
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', [
        'target_url' => 'https://example.com/ignored', 'status' => 'active', 'slug' => 'rota1',
        'wa_mode' => 'weighted',
        'wa_numbers' => [
            ['number' => '+1 (555) 555-0101', 'weight' => '3', 'message' => "Hello\r\nthere"],
            ['number' => '15555550102', 'weight' => '', 'message' => ''],
            ['number' => '', 'weight' => '1', 'message' => ''],
        ],
        'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://example.com/b', 'weight' => 1]],
    ])->assertOk();
    $link = Link::where('slug', 'rota1')->first();
    expect($link->wa_mode)->toBe('weighted')
        ->and($link->wa_numbers)->toBe([
            ['id' => 1, 'number' => '15555550101', 'weight' => 3, 'message' => "Hello\nthere", 'since' => now()->timestamp],
            ['id' => 2, 'number' => '15555550102', 'weight' => 1, 'message' => '', 'since' => now()->timestamp],
        ])
        ->and($link->target_url)->toBe('https://wa.me/15555550101')
        ->and($link->ab_variants)->toBeNull();
});

test('editing keeps the id of a number that stays, so its count stays with it', function () {
    $link = waLink('round_robin', [1, 1]);
    $this->actingAs($link->user)->put('/links/'.$link->id, [
        'target_url' => $link->target_url, 'status' => 'active', 'slug' => $link->slug, 'wa_mode' => 'round_robin',
        'wa_numbers' => [['number' => '15555550102'], ['number' => '15555550109']],
    ])->assertRedirect();
    expect(collect($link->fresh()->wa_numbers)->pluck('id', 'number')->all())->toBe(['15555550102' => 2, '15555550109' => 1]);
    // switching it off turns the link into an ordinary one
    $this->actingAs($link->user)->put('/links/'.$link->id, ['target_url' => 'https://example.com/back', 'status' => 'active', 'slug' => $link->slug, 'wa_mode' => ''])->assertRedirect();
    expect($link->fresh()->wa_mode)->toBeNull()->and($link->fresh()->wa_numbers)->toBeNull();
});

test('the form refuses what is not a rotator, with translated errors', function (array $payload, string $field) {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', $payload + ['target_url' => 'https://example.com/x', 'status' => 'active', 'wa_mode' => 'round_robin'])
        ->assertStatus(422)->assertJsonValidationErrors([$field]);
    expect(Link::count())->toBe(0);
})->with([
    'a national number' => [['wa_numbers' => [['number' => '0555550101']]], 'wa_numbers.0.number'],
    'letters' => [['wa_numbers' => [['number' => 'call me']]], 'wa_numbers.0.number'],
    'the same number twice' => [['wa_numbers' => [['number' => '15555550101'], ['number' => '+1 555 555 0101']]], 'wa_numbers.1.number'],
    'weight 0' => [['wa_numbers' => [['number' => '15555550101', 'weight' => 0]]], 'wa_numbers.0.weight'],
    'weight 101' => [['wa_numbers' => [['number' => '15555550101', 'weight' => 101]]], 'wa_numbers.0.weight'],
    'weight not a number' => [['wa_numbers' => [['number' => '15555550101', 'weight' => 'heavy']]], 'wa_numbers.0.weight'],
    'message too long' => [['wa_numbers' => [['number' => '15555550101', 'message' => str_repeat('a', 501)]]], 'wa_numbers.0.message'],
    'control character' => [['wa_numbers' => [['number' => '15555550101', 'message' => "a\x07b"]]], 'wa_numbers.0.message'],
    'no number at all' => [['wa_numbers' => []], 'wa_numbers'],
    'eleven numbers' => [['wa_numbers' => array_map(fn ($i) => ['number' => '1555555'.str_pad((string) (1000 + $i), 4, '0'), 'weight' => 1], range(1, 11))], 'wa_numbers'],
    'an unknown mode' => [['wa_mode' => 'random', 'wa_numbers' => [['number' => '15555550101']]], 'wa_mode'],
]);

test('the refusal reads in the visitor language', function () {
    $user = User::factory()->create();
    $res = $this->actingAs($user)->postJson('/links?lang=id', ['target_url' => 'https://example.com/x', 'status' => 'active', 'wa_mode' => 'round_robin', 'wa_numbers' => [['number' => '12']]])->assertStatus(422);
    expect($res->json('errors')['wa_numbers.0.number'][0])->toContain('bukan nomor WhatsApp');
});

test('the message goes through the same safety checks as a destination', function () {
    $this->mock(LinkSafety::class, function ($m) {
        $m->shouldReceive('rejectionReason')->withArgs(fn ($url, $text = '') => str_contains($url, 'wa.me/15555550101') && $text === 'buy pills now')->andReturn('This text is not allowed.');
        $m->shouldReceive('rejectionReason')->andReturn(null);
    });
    $this->actingAs(User::factory()->create())->postJson('/links', [
        'target_url' => 'https://example.com/x', 'status' => 'active', 'wa_mode' => 'round_robin',
        'wa_numbers' => [['number' => '15555550101', 'message' => 'buy pills now']],
    ])->assertStatus(422)->assertJsonValidationErrors(['wa_numbers.0.message']);
});

test('a guest cannot make a rotator, through the form or the API', function () {
    $this->post('/shorten', ['target_url' => 'https://github.com/navi-crwn', 'cf-turnstile-response' => 'x', 'wa_mode' => 'round_robin', 'wa_numbers' => [['number' => '15555550101']]])
        ->assertSessionHasErrors('wa_mode');
    expect(Link::count())->toBe(0);
    $this->postJson('/api/shorten', ['url' => 'https://example.com', 'wa_mode' => 'round_robin', 'wa_numbers' => [['number' => '15555550101']]])->assertStatus(401);
    $this->postJson('/links', ['target_url' => 'https://example.com', 'status' => 'active', 'wa_mode' => 'round_robin', 'wa_numbers' => [['number' => '15555550101']]])->assertUnauthorized();
    expect(Link::count())->toBe(0);
});

// --- the screens -----------------------------------------------------------------------------------------------

test('the statistics show the count of each number to the owner, the edit page shows the fields', function () {
    $link = waLink('round_robin', [1, 1]);
    foreach (range(1, 3) as $_) {
        waVisit($link->slug);
    }
    $this->actingAs($link->user)->get('/links/'.$link->id)->assertOk()->assertSee('WhatsApp numbers')->assertSee('+15555550101')->assertSee('+15555550102');
    $this->actingAs($link->user)->get('/links/'.$link->id.'/edit')->assertOk()->assertSee('WhatsApp rotator')->assertSee('wa_mode', false);
    $plain = Link::create(['user_id' => $link->user_id, 'slug' => 'plaintwo', 'target_url' => 'https://example.com/p', 'status' => 'active']);
    $this->actingAs($link->user)->get('/links/'.$plain->id)->assertOk()->assertDontSee('WhatsApp numbers');
});

test('every new string is translated in all nine languages', function () {
    $keys = json_decode(file_get_contents(base_path('tests/fixtures/k3-wa-claim-keys.json')), true);
    foreach ($keys as $key) {
        foreach (Locales::codes() as $lang) {
            $file = json_decode(file_get_contents(base_path("lang/$lang.json")), true);
            expect($file)->toHaveKey($key);
            if ($lang !== 'en') {
                expect($file[$key])->not->toBe($key);
            }
        }
    }
});

// --- ids, and what counts as a number's click ----------------------------------------------------------------

function waEdit(Link $link, array $numbers)
{
    return test()->actingAs($link->user)->put('/links/'.$link->id, [
        'target_url' => $link->target_url, 'status' => 'active', 'slug' => $link->slug, 'wa_mode' => 'round_robin',
        'wa_numbers' => array_map(fn ($n) => ['number' => $n], $numbers),
    ])->assertRedirect();
}

test('number ids stay inside link_clicks.variant (an unsigned tiny integer): the smallest free id is reused', function () {
    $link = waLink('round_robin', [1]);
    $all = array_map(fn ($i) => '1555555'.str_pad((string) (2000 + $i), 4, '0', STR_PAD_LEFT), range(1, 10));
    waEdit($link, $all); // ten numbers: the edge of the list
    expect(collect($link->fresh()->wa_numbers)->pluck('id')->sort()->values()->all())->toBe(range(1, 10));
    // replace one number over and over: its id is handed on, ids never climb
    for ($round = 0; $round < 40; $round++) {
        $all[4] = '1555556'.str_pad((string) (3000 + $round), 4, '0', STR_PAD_LEFT);
        waEdit($link, $all);
    }
    $ids = collect($link->fresh()->wa_numbers)->pluck('id')->sort()->values()->all();
    expect($ids)->toBe(range(1, 10))->and(max($ids))->toBeLessThanOrEqual(255);
    // a number that stays keeps its id when others come and go
    $stay = $link->fresh()->wa_numbers[7];
    waEdit($link, [$stay['number'], '15555559999']);
    $after = collect($link->fresh()->wa_numbers);
    expect($after->firstWhere('number', $stay['number'])['id'])->toBe($stay['id'])
        ->and($after->firstWhere('number', '15555559999')['id'])->toBe(1);
});

test('a click is logged and counted for an id at the edge of the column', function () {
    $link = waLink('round_robin', [1, 1]);
    $numbers = $link->wa_numbers;
    $numbers[1]['id'] = 255;
    $link->update(['wa_numbers' => $numbers]);
    foreach (range(1, 2) as $_) {
        waVisit($link->slug)->assertRedirect();
    }
    expect(LinkClick::where('link_id', $link->id)->pluck('variant')->sort()->values()->all())->toBe([1, 255])
        ->and(WhatsAppRotator::clicks($link->fresh()))->toBe([1 => 1, 255 => 1]);
});

test('a new number that gets a freed id does not inherit the clicks of the number that had it', function () {
    $this->travelTo(now()->startOfSecond());
    $link = waLink('round_robin', [1, 1]);
    $a = $link->wa_numbers[0];
    $b = $link->wa_numbers[1];
    foreach (range(1, 4) as $_) {
        LinkClick::create(['link_id' => $link->id, 'ip_address' => '203.0.113.5', 'user_agent' => 'x', 'variant' => 1, 'clicked_at' => now()]);
    }
    expect(WhatsAppRotator::clicks($link->fresh())[1])->toBe(4);
    $this->travelTo(now()->addMinute());
    waEdit($link, ['15555559001', $b['number']]); // the first number is replaced and takes id 1 again
    $fresh = $link->fresh();
    expect($fresh->wa_numbers[0]['id'])->toBe(1)->and($fresh->wa_numbers[0]['number'])->toBe('15555559001')
        ->and(WhatsAppRotator::clicks($fresh)[1])->toBe(0);
    LinkClick::create(['link_id' => $link->id, 'ip_address' => '203.0.113.5', 'user_agent' => 'x', 'variant' => 1, 'clicked_at' => now()->addSecond()]);
    expect(WhatsAppRotator::clicks($link->fresh())[1])->toBe(1);
});

test('clicks of an earlier A/B split are not counted as clicks of the rotator numbers', function () {
    $this->travelTo(now()->startOfSecond());
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'wasab', 'target_url' => 'https://example.com/m', 'status' => 'active',
        'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 1], ['url' => 'https://example.com/b', 'weight' => 1]]]);
    foreach ([1, 1, 2, 3] as $variant) {
        LinkClick::create(['link_id' => $link->id, 'ip_address' => '203.0.113.5', 'user_agent' => 'x', 'variant' => $variant, 'clicked_at' => now()]);
    }
    $this->travelTo(now()->addMinute());
    $this->actingAs($user)->put('/links/'.$link->id, [
        'target_url' => 'https://example.com/m', 'status' => 'active', 'slug' => 'wasab', 'wa_mode' => 'round_robin',
        'wa_numbers' => [['number' => '15555550101'], ['number' => '15555550102'], ['number' => '15555550103']],
    ])->assertRedirect();
    expect(WhatsAppRotator::clicks($link->fresh()))->toBe([1 => 0, 2 => 0, 3 => 0]);
    waVisit('wasab')->assertRedirect();
    expect(WhatsAppRotator::clicks($link->fresh())[1])->toBe(1);
});
