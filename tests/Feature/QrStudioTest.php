<?php

use App\Models\User;
use App\Support\QrStrings;
use App\Support\ReservedSlugs;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

const QR_LANGS = ['en', 'zh-CN', 'de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru'];

test('a guest opens /qr: the page, the vendored library and no outside host', function () {
    $html = $this->get('/qr')->assertOk()->getContent();
    expect($html)->toContain('id="qr-i18n"')->toContain('vendor/qr-code-styling/qr-code-styling.js')
        ->toContain('id="panel-wifi"')->toContain('id="panel-vcard"')->toContain('id="panel-text"')
        ->toContain('/l/menu26?src=qr');
    // No script or stylesheet from a host other than this one (the CSP has no CDN either).
    preg_match_all('~<(?:script|link)[^>]+(?:src|href)="(https?://[^"/]+)~', $html, $m);
    foreach (array_unique($m[1]) as $origin) {
        $host = (string) parse_url($origin, PHP_URL_HOST);
        expect($host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, 'hel.ink'))->toBeTrue("outside host: {$host}");
    }
});

test('/qr takes no data: no form, no POST route, query values are never echoed', function () {
    $html = $this->get('/qr?pw=hunter2-wifi&ssid=Secret')->assertOk()->getContent();
    // The query is not read: the fields keep their sample values and no markup carries the typed ones.
    expect($html)->toContain('value="hop-wifi-2026"')->not->toContain('value="hunter2-wifi"')->not->toContain('<form');
    $methods = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => $r->uri() === 'qr')->flatMap(fn ($r) => $r->methods())->unique()->values()->all();
    expect($methods)->toContain('GET')->not->toContain('POST')->not->toContain('PUT');
    $this->post('/qr', ['password' => 'hunter2-wifi'])->assertStatus(405);
});

test('a visit leaves no trace of what a visitor would type', function () {
    $lines = [];
    Log::listen(function ($m) use (&$lines) {
        $lines[] = $m->message.json_encode($m->context);
    });
    $this->get('/qr?password=hunter2-wifi');
    expect(implode("\n", $lines))->not->toContain('hunter2-wifi');
});

test('the page follows the visitor language and signed-in users see it too', function () {
    $this->withCookie('hop_lang', 'ja')->get('/qr?lang=ja')->assertOk()->assertSee('QRコードを作る');
    $this->get('/qr?lang=de')->assertSee('QR-Code erstellen');
    $this->actingAs(User::factory()->create())->get('/qr')->assertOk()->assertSee('Make a QR code');
});

test('the QR texts exist in all nine lang files and none is left in English', function () {
    app()->setLocale('en');
    $english = QrStrings::all();
    expect(count($english))->toBeGreaterThan(100);
    foreach (QR_LANGS as $lang) {
        $file = json_decode(file_get_contents(lang_path($lang.'.json')), true);
        app()->setLocale($lang);
        $translated = QrStrings::all();
        foreach ($english as $key => $text) {
            expect($file)->toHaveKey($text);
            if ($lang !== 'en' && strlen($text) > 45) {
                expect($translated[$key])->not->toBe($text, "{$lang}: {$key}");
            }
        }
    }
    app()->setLocale('en');
});

test('the public top bar links to the public QR page and the dashboard menu to the member page', function () {
    $this->get('/')->assertSee('href="'.route('qr').'"', false);
    $html = $this->actingAs(User::factory()->create())->get('/links')->getContent();
    expect($html)->toContain('href="'.route('qr-codes.index').'"')->not->toContain('href="'.route('qr').'"');
});

test('qr is a reserved slug and /qr is the page, not a short link', function () {
    expect(ReservedSlugs::isReserved('qr'))->toBeTrue();
    $this->get('/qr')->assertOk()->assertSee('id="qr-i18n"', false);
});
