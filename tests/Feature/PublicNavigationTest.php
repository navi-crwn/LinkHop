<?php

use Illuminate\Support\Facades\Config;

test('public layout logo points to LinkHop home, not the hub public host', function () {
    Config::set('shortener.public_url', 'https://hel.ink.invalid');

    $html = $this->get('/check')->assertOk()->getContent();

    expect($html)->toContain('href="'.route('home').'"');
    expect($html)->not->toMatch('#<a href="https://hel\.ink\.invalid"[^>]*><x-wordmark#');
    expect($html)->not->toMatch('#href="https://hel\.ink\.invalid/?"#');
});

test('public layout offers a back link to LinkHop home', function () {
    $html = $this->get('/check?return=https://example.invalid')->assertOk()->getContent();

    expect($html)->toContain('Back to LinkHop');
    expect($html)->toContain('href="'.route('home').'"');
    // The back link is static: it never echoes a caller-supplied return address.
    expect($html)->not->toContain('example.invalid');
});
