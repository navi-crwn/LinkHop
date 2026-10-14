<?php

use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\User;
use App\Services\GeoIpService;
use App\Support\BioThemes;
use App\Support\QrPng;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
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

test('design fields only accept colours, gradients and images, not arbitrary css', function () {
    $page = backendPage();
    $put = fn (array $data) => $this->actingAs($page->user)->putJson("/bio/{$page->slug}", $data);

    $put(['title_color' => 'red;}body{display:none'])->assertUnprocessable()->assertJsonValidationErrors('title_color');
    $put(['background_value' => "x') ; } * { background: url(https://evil.test/t.gif"])->assertUnprocessable();
    $put(['font_family' => 'inter;color:red'])->assertUnprocessable();
    $put(['btn_bg_color' => '#fff'])->assertOk(); // unknown on pages, ignored

    $put([
        'title_color' => '#112233',
        'link_bg_color' => 'rgba(99, 102, 241, 0.2)',
        'background_type' => 'gradient',
        'background_value' => 'linear-gradient(135deg, #667eea 0%, rgba(118,75,162,0.8) 100%)',
        'font_family' => 'plus-jakarta-sans',
    ])->assertOk();
    $put(['background_type' => 'image', 'background_value' => '/storage/bio-images/abc123.png'])->assertOk();
    expect($page->fresh()->title_color)->toBe('#112233');
});

test('text longer than its database column is rejected instead of failing on save', function () {
    $page = backendPage();
    $this->actingAs($page->user)->putJson("/bio/{$page->slug}", ['title_color' => '#'.str_repeat('a', 60)])->assertUnprocessable();
    $this->actingAs($page->user)->postJson("/bio/{$page->slug}/links", ['type' => 'link', 'title' => 'x', 'url' => 'https://example.com/'.str_repeat('a', 600)])
        ->assertUnprocessable()->assertJsonValidationErrors('url');
});

test('the slug checker agrees with saving', function () {
    $page = backendPage(['slug' => 'mypage']);
    $check = fn (array $q) => $this->actingAs($page->user)->getJson('/bio/check-slug?'.http_build_query($q))->json('available');

    expect($check(['slug' => 'freeslug1']))->toBeTrue()
        ->and($check(['slug' => str_repeat('a', 21)]))->toBeFalse() // too long for save
        ->and($check(['slug' => 'mypage']))->toBeFalse()
        ->and($check(['slug' => 'mypage', 'current' => 'mypage']))->toBeTrue()
        ->and($check(['slug' => 'MyPage', 'current' => 'mypage']))->toBeTrue();
});

test('socials are stored as a json array, not a json string', function () {
    $page = backendPage();
    $this->actingAs($page->user)->putJson("/bio/{$page->slug}", ['socials' => ['position' => 'top']])->assertOk();
    expect($page->fresh()->socials)->toBe(['position' => 'top']);
});

test('a save from a stale editor tab is rejected instead of overwriting the newer one', function () {
    $page = backendPage();
    $this->travel(-5)->minutes();
    $page->touch();
    $this->travelBack();
    $stale = $page->fresh()->updated_at->toJSON();

    // The other tab adds a block: that counts as a page change.
    $added = $this->actingAs($page->user)->postJson(route('bio.links.store', $page), ['type' => 'divider'])
        ->assertOk()->json('page_updated_at');
    expect($added)->not->toBe($stale);

    $this->actingAs($page->user)->putJson(route('bio.update', $page), ['title' => 'Old tab', 'base_updated_at' => $stale])
        ->assertStatus(409)->assertJsonPath('conflict', true);
    expect($page->fresh()->title)->toBe('Page');

    $this->actingAs($page->user)->putJson(route('bio.update', $page), ['title' => 'Current tab', 'base_updated_at' => $added])
        ->assertOk();
    expect($page->fresh()->title)->toBe('Current tab');
});

test('the editor, preview and public page use the same theme colours, and every theme saves', function () {
    $page = backendPage();
    $picker = BioThemes::picker();
    foreach ($picker as $id => $theme) {
        // What applyTheme() in the editor sends.
        $gradient = str_contains($theme['background'], 'gradient');
        $this->actingAs($page->user)->putJson("/bio/{$page->slug}", [
            'theme' => $id,
            'background_type' => $gradient ? 'gradient' : 'solid',
            'background_value' => $theme['background'],
            ($gradient ? 'background_gradient' : 'background_color') => $theme['background'],
            'title_color' => $theme['text'],
            'bio_color' => $theme['bio'],
            'link_bg_color' => $theme['link_bg'],
            'button_bg_color' => $theme['link_bg'],
            'link_text_color' => $theme['link_text'],
            'button_text_color' => $theme['link_text'],
        ])->assertOk();
    }

    $sunset = config('bio-themes.presets.sunset');
    expect($picker['sunset']['background'])->toBe($sunset['background']);
    $page->update(['theme' => 'sunset']);
    $this->get("/b/{$page->slug}")->assertOk()->assertSee($sunset['background'], false);
    $this->actingAs($page->user)->get("/bio/{$page->slug}/edit")->assertOk()->assertSee(json_encode($sunset['background']), false);
});

test('embeds that play in the preview also play on the public page', function () {
    $page = backendPage();
    $cases = [
        ['video', 'https://youtube.com/shorts/abcDEF12345', 'https://www.youtube.com/embed/abcDEF12345?rel=0'],
        ['video', 'https://www.tiktok.com/@someone/video/7234567890123456789', 'https://www.tiktok.com/embed/v2/7234567890123456789'],
        ['video', 'https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871?dnt=1'],
        ['music', 'https://music.apple.com/us/album/some-album/1440857781', 'https://embed.music.apple.com/us/album/1440857781'],
        ['music', 'https://open.spotify.com/episode/4rOoJ6Egrf8K2IrywzwOMk', 'https://open.spotify.com/embed/episode/4rOoJ6Egrf8K2IrywzwOMk'],
        ['youtube', 'https://www.youtube.com/live/zyx987_ABCD', 'https://www.youtube.com/embed/zyx987_ABCD?rel=0'],
    ];
    foreach ($cases as $i => [$type, $url]) {
        $page->links()->create(['type' => $type, 'title' => "Embed {$i}", 'embed_url' => $url, 'order' => $i, 'is_active' => true]);
    }

    $response = $this->get("/b/{$page->slug}")->assertOk();
    foreach ($cases as [, , $src]) {
        $response->assertSee('src="'.e($src), false);
    }
    $response->assertSee('loading="lazy"', false);
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain('https://www.tiktok.com')->toContain('https://embed.music.apple.com')->toContain('https://player.vimeo.com');
});

test('a countdown keeps the exact moment the owner picked and the page gives it to browsers in a format every browser parses', function () {
    $page = backendPage();
    // 10:00 in Jakarta (UTC+7), as the editor sends it.
    $id = $this->actingAs($page->user)->postJson("/bio/{$page->slug}/links", [
        'type' => 'countdown', 'countdown_label' => 'Launch', 'countdown_date' => '2030-01-02T03:00:00.000Z',
    ])->assertOk()->json('block.id');

    expect(BioLink::find($id)->countdown_date->utc()->format('Y-m-d H:i'))->toBe('2030-01-02 03:00');
    $this->get("/b/{$page->slug}")->assertOk()->assertSee('data-target="2030-01-02T03:00:00Z"', false);
});

test('the editor, the create form and the server share the title and bio limits', function () {
    $page = backendPage(['title' => str_repeat('n', 40)]);
    $editor = $this->actingAs($page->user)->get("/bio/{$page->slug}/edit")->assertOk();
    $editor->assertSee('maxlength="'.BioPage::TITLE_MAX.'"', false)->assertSee('maxlength="'.BioPage::BIO_MAX.'"', false);
    $this->actingAs($page->user)->get('/bio/create')->assertSee('maxlength="'.BioPage::TITLE_MAX.'"', false);

    $this->actingAs($page->user)->putJson("/bio/{$page->slug}", ['title' => str_repeat('n', BioPage::TITLE_MAX)])->assertOk();
    $this->actingAs($page->user)->putJson("/bio/{$page->slug}", ['title' => str_repeat('n', BioPage::TITLE_MAX + 1)])->assertUnprocessable();
});

test('no view uses inline event handler attributes, so a strict script-src-attr policy breaks nothing', function () {
    $offenders = [];
    foreach (File::allFiles(resource_path('views')) as $file) {
        foreach (preg_split('/\R/', $file->getContents()) as $n => $line) {
            if (preg_match('/(?<![\w:@.-])on[a-z]+\s*=\s*["\']/', $line)) {
                $offenders[] = $file->getRelativePathname().':'.($n + 1);
            }
        }
    }
    expect($offenders)->toBe([]);
});

test('the public bio page loads Alpine once and the QR library only when sharing', function () {
    $page = backendPage();
    $html = $this->get("/b/{$page->slug}")->assertOk()->getContent();
    expect($html)->not->toContain('alpinejs@')
        ->not->toMatch('/<script[^>]+qr-code-styling/');
});

test('bio page trackers wait for the visitor to allow them', function () {
    $page = backendPage(['facebook_pixel_id' => '1234567890123', 'tiktok_pixel_id' => 'ABCDEFGHIJ1234567890', 'google_analytics_id' => 'G-ABC123']);
    $html = $this->get("/b/{$page->slug}")->assertOk()->getContent();

    expect($html)->toContain('id="tracker-consent"')
        ->toContain('Google Analytics, Facebook, TikTok')
        ->not->toMatch('/<script[^>]+src="https:\/\/www\.googletagmanager/')
        ->not->toContain('facebook.com/tr?id=');

    $plain = backendPage();
    expect($this->get("/b/{$plain->slug}")->getContent())->not->toContain('tracker-consent');
});
