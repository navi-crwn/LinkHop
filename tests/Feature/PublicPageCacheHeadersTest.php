<?php

/*
| Pages that follow the visitor's cookies (language, sign-in) say so with `Vary: Cookie` (family contract,
| KONTRAK-SEO-BAHASA rule 6). `Cache-Control` stays `no-cache, private`. Public assets keep their own policy and
| get no `Vary`: they do not follow a cookie.
*/

dataset('public paths', ['/about', '/']);

it('sends Vary: Cookie and a private policy on pages that follow the cookie', function (string $path) {
    $variants = [
        fn () => $this->get("https://link.hel.ink$path"),
        fn () => $this->withUnencryptedCookie('hop_lang', 'id')->get("https://link.hel.ink$path"),
        fn () => $this->get("https://link.hel.ink$path".'?lang=id'),
    ];
    foreach ($variants as $call) {
        $response = $call()->assertOk();
        expect((string) $response->headers->get('Cache-Control'))->toContain('private')->toContain('no-cache')
            ->and((string) $response->headers->get('Vary'))->toContain('Cookie');
    }
})->with('public paths');

it('leaves a public asset without Vary and with its own policy', function () {
    $response = $this->get(route('brand.favicon', ['v' => str_repeat('a', 8)]));

    expect($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->has('Vary'))->toBeFalse();
});
