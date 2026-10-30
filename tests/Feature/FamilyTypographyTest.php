<?php

test('public pages load the HOP family fonts from hel.ink, not a font CDN', function () {
    foreach (['/', '/check', '/report-abuse'] as $path) {
        $html = $this->get($path)->assertOk()->getContent();

        expect($html)->toContain('https://hel.ink/hop-assets/v1/fonts.css')
            ->toContain('integrity="sha384-')
            ->not->toContain('fonts.bunny.net');
    }
});

test('the footer lists the HOP family from the shared manifest', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('HOP family')
        ->toContain('href="https://bio.hel.ink"')
        ->toContain('href="https://p.hel.ink"')
        ->toContain('href="https://note.hel.ink"')
        ->not->toContain('features.php');
});

test('the content security policy allows the family assets on hel.ink', function () {
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->toMatch('#font-src [^;]*https://hel\\.ink#')
        ->toMatch('#style-src [^;]*https://hel\\.ink#');
});
