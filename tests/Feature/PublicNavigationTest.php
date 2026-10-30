<?php

use Illuminate\Support\Facades\Config;

test('public layout logo points to LinkHop home, not the hub public host', function () {
    Config::set('shortener.public_url', 'https://hel.ink.invalid');

    $html = $this->get('/check?return=https://example.invalid')->assertOk()->getContent();

    // The public host legitimately appears as display text (slug placeholder,
    // checker copy); only link targets must stay on LinkHop home, never on the
    // hub public host.
    expect($html)->toContain('href="'.route('home').'"');
    expect($html)->not->toMatch('#href="https://hel\.ink\.invalid#');
});

test('public layout offers an explicit static back link to LinkHop home', function () {
    $html = $this->get('/check?return=https://example.invalid')->assertOk()->getContent();

    expect($html)->toContain('Kembali ke LinkHop');
    // The back link goes home; an arbitrary ?return= is never followed.
    expect($html)->not->toContain('example.invalid');
});

test('marketing nav labels hub links as the HOP hub interim', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('Products — HOP hub');
    expect($html)->toContain('Help — HOP hub');
    expect($html)->toContain('FAQ — HOP hub');
});

test('marketing footer labels hub links as the HOP hub interim', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('About — HOP hub');
    expect($html)->toContain('Contact — HOP hub');
    expect($html)->toContain('Privacy policy — HOP hub');
    expect($html)->toContain('Terms of service — HOP hub');
});
