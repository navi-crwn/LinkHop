<?php

use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\User;
use App\Services\GeoIpService;
use App\Support\QrPng;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

function backendPage(array $attrs = []): BioPage
{
    return BioPage::create(array_merge([
        'user_id' => User::factory()->create()->id,
        'slug' => 'bk'.strtolower(Str::random(6)),
        'title' => 'Page',
        'theme' => 'default',
        'is_published' => true,
    ], $attrs));
}

test('geoip tries at most two web apis for a new ip and skips a failing one afterwards', function () {
    config(['services.geolite.city_db' => null]);
    Http::fake(fn () => Http::response('', 500));

    $geo = app(GeoIpService::class);
    expect($geo->country('8.8.8.8'))->toBeNull();
    Http::assertSentCount(2);

    // Both failed: they cool down, so the next new IP costs no request at all to them.
    expect($geo->country('1.1.1.1'))->toBeNull();
    $hosts = collect(Http::recorded())->map(fn ($pair) => parse_url($pair[0]->url(), PHP_URL_HOST));
    expect($hosts->count())->toBeLessThanOrEqual(4)->and($hosts->duplicates())->toBeEmpty();
});

test('geoip never sends private addresses to web apis and does not log ip addresses', function () {
    config(['services.geolite.city_db' => null]);
    Http::fake(fn () => Http::response('', 500));
    $logged = [];
    Log::listen(function ($event) use (&$logged) {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    expect(app(GeoIpService::class)->country('192.168.1.20'))->toBeNull();
    Http::assertNothingSent();

    app(GeoIpService::class)->country('9.9.9.9');
    expect($logged)->not->toBeEmpty();
    foreach ($logged as $line) {
        expect($line)->not->toContain('9.9.9.9');
    }
});

test('geoip remembers an unresolved ip instead of looking it up again', function () {
    config(['services.geolite.city_db' => null]);
    Http::fake(fn () => Http::response('', 500));
    app(GeoIpService::class)->country('8.8.4.4');
    Cache::forget('geoip:down:freeipapi');
    $sent = count(Http::recorded());
    app(GeoIpService::class)->country('8.8.4.4');
    expect(count(Http::recorded()))->toBe($sent);
});

test('qr png is generated without imagick', function () {
    $png = QrPng::render('https://hel.ink/b/test', 300);
    $info = getimagesizefromstring($png);
    expect($info[0])->toBe(300)->and($info['mime'])->toBe('image/png');
});

test('the public qr code works and an oversized logo is ignored instead of decoded', function () {
    $page = backendPage();
    $this->get("/b/{$page->slug}/qr")->assertOk()->assertHeader('Content-Type', 'image/png');

    // 4000 x 4000 would need 64 MB once decoded.
    $big = imagecreatetruecolor(4000, 4000);
    ob_start();
    imagepng($big, null, 9);
    $logo = 'data:image/png;base64,'.base64_encode(ob_get_clean());
    $this->get('/api/qr-generate?'.http_build_query(['data' => 'https://hel.ink', 'size' => 200, 'logo' => $logo]))
        ->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(QrPng::logo($logo))->toBeNull();
});

test('bio analytics accepts only the offered ranges and ranks top links by clicks', function () {
    $page = backendPage();
    BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'First position', 'url' => 'https://example.com/a', 'is_active' => true, 'order' => 0, 'click_count' => 1]);
    BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'Most clicked', 'url' => 'https://example.com/b', 'is_active' => true, 'order' => 1, 'click_count' => 50]);

    $this->actingAs($page->user)->get("/bio/{$page->slug}/analytics?days=999999")->assertOk();
    $this->actingAs($page->user)->get("/bio/{$page->slug}/analytics?days=abc")->assertOk();
    $this->actingAs($page->user)->get("/bio/{$page->slug}/analytics?days=7")
        ->assertOk()
        ->assertSeeInOrder(['Most clicked', 'First position'])
        ->assertSee('Clicks Over Time');
});

test('reordering and toggling blocks need a verified email like other edits', function () {
    $page = backendPage();
    $page->user->forceFill(['email_verified_at' => null])->save();
    $block = BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'A', 'url' => 'https://example.com', 'is_active' => true, 'order' => 0]);

    $this->actingAs($page->user)->postJson("/bio/{$page->slug}/links/reorder", ['links' => [['id' => $block->id, 'order' => 0]]])->assertForbidden();
    $this->actingAs($page->user)->postJson("/bio/{$page->slug}/links/{$block->id}/toggle")->assertForbidden();
});
