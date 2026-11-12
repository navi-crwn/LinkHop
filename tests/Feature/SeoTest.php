<?php

use App\Models\Link;
use App\Models\User;
use Hop\Ui\Locales;
use Illuminate\Support\Facades\Hash;

/*
| What search engines read: every public page names one absolute canonical address (its path, plus ?lang= for a
| language other than English, never another query string), the nine language alternates and x-default, og:url,
| an absolute og:image and a twitter card. The home page and /qr say what they do and carry WebApplication JSON-LD.
| Signed-in pages and every short-link answer (redirect, preview, password, ended, missing) are noindex, and the
| sitemap lists the public pages and nothing else.
*/
beforeEach(function () {
    config(['app.url' => 'https://link.hel.ink', 'shortener.public_url' => 'https://hel.ink']);
});

/** @return array<string, string> the content of each <meta name|property=… content=…> of a page */
function seoMeta(string $html): array
{
    preg_match_all('~<meta\s+(?:name|property)="([^"]+)"\s+content="([^"]*)"~', $html, $m, PREG_SET_ORDER);

    return collect($m)->mapWithKeys(fn ($x) => [$x[1] => html_entity_decode($x[2], ENT_QUOTES)])->all();
}

function seoCanonical(string $html): ?string
{
    return preg_match('~<link rel="canonical" href="([^"]+)">~', $html, $m) ? html_entity_decode($m[1]) : null;
}

/** @return list<array<string, mixed>> */
function seoJsonLd(string $html): array
{
    preg_match_all('~<script type="application/ld\+json"[^>]*>(.*?)</script>~s', $html, $m);

    return array_map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $m[1]);
}

it('names an absolute canonical without the query string, og:url, an absolute og:image and a card on every public page', function (string $path) {
    $html = $this->get($path.'?utm_source=x&ref=y')->assertOk()->getContent();
    $expected = 'https://link.hel.ink/'.ltrim($path, '/');
    $meta = seoMeta($html);

    expect(seoCanonical($html))->toBe($expected)
        ->and($meta['og:url'] ?? null)->toBe($expected)
        ->and($meta['og:image'] ?? '')->toStartWith('http')
        ->and($meta['twitter:card'] ?? null)->toBe('summary')
        ->and($meta['robots'] ?? null)->toBeNull();
    // Family rule (KONTRAK-SEO-BAHASA): ?lang= with one of the nine codes is kept, nothing else.
    expect(seoCanonical($this->get($path.'?lang=de')->getContent()))->toBe($expected.'?lang=de')
        ->and(seoCanonical($this->get($path.'?lang=de&utm_source=x')->getContent()))->toBe($expected.'?lang=de')
        ->and(seoCanonical($this->get($path.'?utm_source=x&lang=pt')->getContent()))->toBe($expected.'?lang=pt-BR')
        ->and(seoCanonical($this->get($path.'?lang=xx')->getContent()))->toBe($expected);
})->with(['/', '/qr', '/features', '/about', '/help', '/faq', '/contact', '/terms', '/privacy', '/docs', '/status', '/changelog', '/check']);

it('builds the canonical from APP_URL, never from the host the request came in on', function () {
    $html = $this->get('http://other.example.test/faq?lang=fr')->assertOk()->getContent();

    expect(seoCanonical($html))->toBe('https://link.hel.ink/faq?lang=fr')
        ->and(seoMeta($html)['og:url'])->toBe('https://link.hel.ink/faq?lang=fr');
});

it('lists the nine languages and x-default as alternates, because ?lang= serves each language at its own address', function () {
    $html = $this->withUnencryptedCookie('hop_lang', 'de')->get('/qr')->assertOk()->getContent();
    preg_match_all('~<link rel="alternate" hreflang="([^"]+)" href="([^"]+)">~', $html, $m);
    $alternates = array_combine($m[1], array_map('html_entity_decode', $m[2]));

    expect($alternates)->toHaveCount(10)
        ->and(array_keys($alternates))->toBe([...Locales::codes(), 'x-default'])
        ->and($alternates['en'])->toBe('https://link.hel.ink/qr?lang=en')
        ->and($alternates['ja'])->toBe('https://link.hel.ink/qr?lang=ja')
        ->and($alternates['x-default'])->toBe('https://link.hel.ink/qr');
    // and ?lang= gives that language whatever the cookie says
    $this->get('/qr?lang=ja')->assertOk()->assertSee('lang="ja"', false);
    $this->get('/qr?lang=en')->assertOk()->assertSee('lang="en"', false);
});

it('says what the home page and /qr do, in all nine languages, with WebApplication data', function () {
    foreach (Locales::codes() as $lang) {
        $this->flushSession();
        $home = $this->get('/?lang='.$lang)->assertOk()->getContent();
        $meta = seoMeta($home);
        expect($home)->toContain('<title>LinkHop · '.e(trans('Free link shortener with QR codes and click stats', [], $lang)).'</title>')
            ->and($meta['description'])->toBe(trans('Shorten long links for free: your own short address, a QR code for every link and click stats. Start without an account; LinkHop is open source.', [], $lang))
            ->and($meta['og:description'])->toBe($meta['description']);

        $qr = $this->get('/qr?lang='.$lang)->assertOk()->getContent();
        expect($qr)->toContain('<title>'.e(trans('Make a QR code', [], $lang).' '.trans('in your browser', [], $lang)).' · LinkHop</title>')
            ->and(seoMeta($qr)['description'])->toBe(trans('Wi-Fi, contact card, link or text. The code is drawn on this page: what you type is never sent to a server.', [], $lang));
        if ($lang !== 'en') {
            expect($meta['description'])->not->toBe(trans('Shorten long links for free: your own short address, a QR code for every link and click stats. Start without an account; LinkHop is open source.', [], 'en'));
        }
    }

    foreach (['/' => 'https://link.hel.ink/', '/qr' => 'https://link.hel.ink/qr'] as $path => $url) {
        $data = seoJsonLd($this->get($path)->getContent());
        expect($data)->toHaveCount(1)
            ->and($data[0]['@type'])->toBe('WebApplication')
            ->and($data[0]['url'])->toBe($url)
            ->and($data[0]['isAccessibleForFree'])->toBeTrue()
            ->and($data[0]['inLanguage'])->toBe(Locales::codes());
    }
    expect($this->get('/')->getContent())->not->toContain('lightweight for guests and teams');
});

it('keeps the signed-in pages and the API docs out of search results', function (string $path) {
    $this->actingAs(User::factory()->create());
    $html = $this->get($path)->assertOk()->getContent();

    expect(seoMeta($html)['robots'] ?? null)->toBe('noindex, nofollow')->and(seoCanonical($html))->toBeNull()
        ->and($html)->not->toContain('hreflang="x-default"');
})->with(['/dashboard', '/links', '/analytics', '/settings', '/api-docs', '/links/create']);

it('lets no short-link answer be indexed: redirect, legacy address, preview, password, ended and missing', function () {
    $user = User::factory()->create();
    $make = fn (string $slug, array $extra = []) => Link::create(['user_id' => $user->id, 'slug' => $slug, 'target_url' => 'https://example.com/'.$slug, 'status' => 'active'] + $extra);
    $make('plain1');
    $make('previewed')->forceFill(['enable_preview' => true])->save();
    $make('locked1', ['password_hash' => Hash::make('secret123')]);
    $make('ended1', ['expires_at' => now()->subDay()]);
    $make('social1', ['custom_title' => 'A card']);

    $answers = [
        $this->get('/l/plain1')->assertRedirect('https://example.com/plain1'),
        $this->get('/plain1')->assertStatus(301),
        $this->get('/l/previewed')->assertOk()->assertSee('https://example.com/previewed', false),
        $this->get('/l/locked1')->assertOk(),
        $this->get('/l/ended1')->assertStatus(410),
        $this->get('/l/nosuchlink')->assertNotFound(),
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get('/l/social1')->assertOk(),
    ];
    foreach ($answers as $i => $response) {
        expect($response->headers->get('X-Robots-Tag'))->toStartWith('noindex', "answer {$i}");
    }
});

it('marks the error pages noindex and gives them no canonical', function () {
    $html = $this->get('/no/such/page')->assertNotFound()->getContent();

    expect(seoMeta($html)['robots'] ?? null)->toBe('noindex')->and(seoCanonical($html))->toBeNull()
        ->and($html)->not->toContain('hreflang="x-default"');
});

it('lists every public page in the sitemap, with its language alternates, and no account page', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    preg_match_all('~<loc>([^<]+)</loc>~', $xml, $m);
    $locs = $m[1];

    foreach (['', 'qr', 'features', 'about', 'help', 'faq', 'contact', 'terms', 'privacy', 'docs', 'status', 'changelog', 'check'] as $path) {
        expect($locs)->toContain('https://link.hel.ink/'.$path);
    }
    foreach (['dashboard', 'links', 'analytics', 'settings', 'api-docs', 'report-abuse', 'login'] as $path) {
        expect($locs)->not->toContain('https://link.hel.ink/'.$path);
    }
    expect($xml)->toContain('<xhtml:link rel="alternate" hreflang="de" href="https://link.hel.ink/qr?lang=de"/>')
        ->toContain('<xhtml:link rel="alternate" hreflang="x-default" href="https://link.hel.ink/qr"/>')
        ->and(count($locs))->toBe(count(array_unique($locs)));
    // each <url> carries the ten alternates
    expect(substr_count($xml, '<xhtml:link'))->toBe(10 * count($locs));
});
