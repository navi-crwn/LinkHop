<?php

use App\Models\User;
use Symfony\Component\HttpFoundation\Cookie;

/*
| The family preference cookies (HOP preference contract, train 4). hop_lang: plain text, Domain=.hel.ink and Secure
| on hel.ink and its subdomains, host-only elsewhere; a browser's old host-only hop_lang is moved to the domain once
| (hop_pref=2). hop_theme: written and read only by the first-paint script (resources/js/theme-boot.js, see
| tests/js/theme-boot.test.mjs), which every page that can switch the theme carries.
*/

/** @return list<Cookie> the response's cookies with this name */
function prefCookies($response, string $name): array
{
    return array_values(array_filter($response->headers->getCookies(), fn (Cookie $c) => $c->getName() === $name));
}

it('writes hop_lang for the whole family on a hel.ink host, plain text', function () {
    foreach (['https://link.hel.ink/about?lang=de', 'https://hel.ink/about?lang=de'] as $url) {
        $response = $this->get($url)->assertOk();
        $domain = collect(prefCookies($response, 'hop_lang'))->first(fn (Cookie $c) => $c->getDomain() === '.hel.ink');

        expect($domain)->not->toBeNull($url)
            ->and($domain->getValue())->toBe('de')
            ->and($domain->isSecure())->toBeTrue()
            ->and($domain->isHttpOnly())->toBeFalse()
            ->and($domain->getPath())->toBe('/')
            ->and($domain->getSameSite())->toBe('lax')
            ->and($domain->getMaxAge())->toBeGreaterThan(31535000);
    }
});

it('keeps hop_lang host-only on localhost and self-hosted copies, and migrates nothing there', function () {
    foreach (['http://localhost/about?lang=fr', 'https://links.example.com/about?lang=fr', 'https://nothel.ink/about?lang=fr'] as $url) {
        $response = $this->get($url)->assertOk();
        $cookies = prefCookies($response, 'hop_lang');

        expect($cookies)->toHaveCount(1, $url)
            ->and($cookies[0]->getDomain())->toBeNull()
            ->and($cookies[0]->getValue())->toBe('fr')
            ->and($cookies[0]->isHttpOnly())->toBeFalse()
            ->and(prefCookies($response, 'hop_pref'))->toBe([]);
    }
    expect(prefCookies($this->get('http://localhost/about')->assertOk(), 'hop_lang'))->toBe([]);
});

it('moves an old host-only hop_lang to the family domain once', function () {
    $response = $this->withUnencryptedCookie('hop_lang', 'ja')->get('https://link.hel.ink/about')->assertOk()->assertSee('lang="ja"', false);
    $byDomain = collect(prefCookies($response, 'hop_lang'))->keyBy(fn (Cookie $c) => (string) $c->getDomain());

    // (a) the host-only one expires, (b) the domain one carries its value, (c) the marker is set host-only
    expect($byDomain->keys()->sort()->values()->all())->toBe(['', '.hel.ink'])
        ->and($byDomain['']->isCleared())->toBeTrue()
        ->and($byDomain['.hel.ink']->getValue())->toBe('ja')
        ->and($byDomain['.hel.ink']->isCleared())->toBeFalse();
    $marker = prefCookies($response, 'hop_pref');
    expect($marker)->toHaveCount(1)->and($marker[0]->getValue())->toBe('2')->and($marker[0]->getDomain())->toBeNull()
        ->and($marker[0]->getMaxAge())->toBeGreaterThan(31535000);

});

it('does not migrate twice', function () {
    $again = $this->withUnencryptedCookie('hop_lang', 'ja')->withUnencryptedCookie('hop_pref', '2')->get('https://link.hel.ink/about')->assertOk();

    expect(prefCookies($again, 'hop_lang'))->toBe([])->and(prefCookies($again, 'hop_pref'))->toBe([]);
});

it('lets ?lang= win over the old value while migrating', function () {
    $response = $this->withUnencryptedCookie('hop_lang', 'ja')->get('https://link.hel.ink/about?lang=es')->assertOk()->assertSee('lang="es"', false);
    $live = collect(prefCookies($response, 'hop_lang'))->reject(fn (Cookie $c) => $c->isCleared());

    expect($live->map->getValue()->values()->all())->toBe(['es'])->and($live->first()->getDomain())->toBe('.hel.ink');
});

/*
| A browser that holds this host's old host-only hop_lang and a newer .hel.ink one sends both, oldest first
| ("hop_lang=en; hop_lang=ja"). PHP keeps the first; the last one counts (contract, amended after the audit).
*/
function langLive($response): array
{
    return collect(prefCookies($response, 'hop_lang'))->reject(fn (Cookie $c) => $c->isCleared())
        ->map(fn (Cookie $c) => [$c->getDomain(), $c->getValue()])->values()->all();
}

it('takes the last hop_lang when the browser sends two, for the page and for the clean-up', function () {
    $response = $this->withHeader('Cookie', 'hop_lang=en; other=1; hop_lang=ja')->get('https://link.hel.ink/about')
        ->assertOk()->assertSee('lang="ja"', false);

    expect(langLive($response))->toBe([['.hel.ink', 'ja']])
        ->and(collect(prefCookies($response, 'hop_lang'))->first(fn (Cookie $c) => $c->getDomain() === null)?->isCleared())->toBeTrue()
        ->and(prefCookies($response, 'hop_pref')[0]->getValue())->toBe('2');
    // after the clean-up (marker set) the page still follows the newer one
    $this->flushHeaders()->withHeader('Cookie', 'hop_pref=2; hop_lang=en; hop_lang=fr')->get('https://link.hel.ink/about')->assertSee('lang="fr"', false);
});

it('keeps the single-cookie behaviour', function () {
    $response = $this->withHeader('Cookie', 'hop_lang=de')->withUnencryptedCookie('hop_lang', 'de')->get('https://link.hel.ink/about')
        ->assertOk()->assertSee('lang="de"', false);

    expect(langLive($response))->toBe([['.hel.ink', 'de']]);
});

it('lets ?lang= win over two cookies', function () {
    $response = $this->withHeader('Cookie', 'hop_lang=en; hop_lang=ja')->get('https://link.hel.ink/about?lang=ru')
        ->assertOk()->assertSee('lang="ru"', false);

    expect(langLive($response))->toBe([['.hel.ink', 'ru']]);
});

it('survives a malformed cookie header', function (string $header, string $lang) {
    $response = $this->withHeader('Cookie', $header)->get('https://link.hel.ink/about')->assertOk()->assertSee('lang="'.$lang.'"', false);

    expect(langLive($response))->toBe($lang === 'en' ? [] : [['.hel.ink', $lang]]);
})->with([
    'no value' => ['hop_lang; hop_lang', 'en'],
    'empty and junk' => ['=; ;;hop_lang=; hop_lang=%%zz', 'en'],
    'unknown language last' => ['hop_lang=de; hop_lang=xx', 'en'],
    'spaces and encoding' => ['  hop_lang = de ;hop_lang=pt%2DBR', 'pt-BR'],
]);

it('reads the plain hop_lang a Go app or a script wrote', function () {
    $this->withUnencryptedCookie('hop_lang', 'pt-BR')->withUnencryptedCookie('hop_pref', '2')->get('/about')->assertOk()->assertSee('lang="pt-BR"', false);
});

it('puts the hop_theme reader on every page that can switch the theme', function () {
    $reader = 'hop_theme=';
    $this->get('/')->assertOk()->assertSee($reader, false)->assertSee('data-old-key="helink-theme"', false);
    $this->get('/qr')->assertOk()->assertSee($reader, false);
    $this->get('/about')->assertOk()->assertSee($reader, false);
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertSee($reader, false)->assertSee('window.hopSetTheme', false);
    expect(view('errors.500')->render())->toContain($reader);
    expect(view('preview', ['targetUrl' => 'https://example.com'])->render())->toContain($reader)->toContain('data-old-key="theme"');
});

/*
| H-1: a request that carries no hop_lang has nothing to move, so it gets no clean-up and no cookie at all (a plain
| public page stays free of Set-Cookie named hop_*, as in BioHop). The clean-up runs when hop_lang arrives.
*/
it('sends no hop_* cookie on a request without hop_lang', function () {
    foreach (['https://link.hel.ink/about', 'https://link.hel.ink/', 'https://hel.ink/about'] as $url) {
        $response = $this->get($url)->assertOk();

        expect(prefCookies($response, 'hop_lang'))->toBe([], $url)->and(prefCookies($response, 'hop_pref'))->toBe([], $url);
    }
});

it('treats an empty hop_lang like no cookie', function () {
    $response = $this->withHeader('Cookie', 'hop_lang=')->get('https://link.hel.ink/about')->assertOk();

    expect(prefCookies($response, 'hop_lang'))->toBe([])->and(prefCookies($response, 'hop_pref'))->toBe([]);
});

it('writes exactly one domain hop_lang and no marker for ?lang= without a cookie', function () {
    $response = $this->get('https://link.hel.ink/about?lang=de')->assertOk()->assertSee('lang="de"', false);
    $cookies = prefCookies($response, 'hop_lang');

    expect($cookies)->toHaveCount(1)
        ->and($cookies[0]->getDomain())->toBe('.hel.ink')
        ->and($cookies[0]->getValue())->toBe('de')
        ->and(prefCookies($response, 'hop_pref'))->toBe([]);
});

it('still cleans up when the request carries a hop_lang, even an unsupported one', function () {
    foreach (['ja', 'xx'] as $lang) {
        $response = $this->flushHeaders()->withUnencryptedCookie('hop_lang', $lang)->get('https://link.hel.ink/about')->assertOk();

        expect(prefCookies($response, 'hop_pref'))->toHaveCount(1, $lang)
            ->and(collect(prefCookies($response, 'hop_lang'))->contains(fn (Cookie $c) => $c->isCleared()))->toBeTrue($lang);
    }
});
