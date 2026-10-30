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

    expect($html)->toContain('Produk — HOP hub (sementara)');
    expect($html)->toContain('Bantuan — HOP hub (sementara)');
    expect($html)->toContain('FAQ — HOP hub (sementara)');
});

test('marketing nav marks hub links explicitly temporary', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // The hub detour is explicitly temporary, not just a named hub.
    expect($html)->toContain('sementara');
});

test('marketing footer marks hub links explicitly temporary', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('sementara');
});

test('compact public-layout footer labels its hub destinations explicitly temporary', function () {
    $html = $this->get('/check?return=https://example.invalid')->assertOk()->getContent();

    expect($html)->toContain('Ketentuan — HOP hub (sementara)');
    expect($html)->toContain('Privasi — HOP hub (sementara)');
    expect($html)->toContain('Bantuan — HOP hub (sementara)');
    // The local report-abuse route stays unlabeled: it is not a hub detour.
    expect($html)->not->toContain('Laporkan penyalahgunaan — HOP hub');
});

test('marketing footer labels hub links as the HOP hub interim', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('Tentang — HOP hub (sementara)');
    expect($html)->toContain('Kontak — HOP hub (sementara)');
    expect($html)->toContain('Kebijakan privasi — HOP hub (sementara)');
    expect($html)->toContain('Ketentuan layanan — HOP hub (sementara)');
});
