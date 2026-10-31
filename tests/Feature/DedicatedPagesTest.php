<?php

use App\Http\Controllers\PageController;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('every dedicated page is served on LinkHop with a back link home', function (string $path, string $text) {
    $html = $this->get($path)->assertOk()->getContent();

    expect($html)->toContain($text)
        ->toContain('Back to LinkHop')
        ->toContain('href="'.route('home').'"');
})->with([
    ['/features', 'No premium tier'],
    ['/about', 'About LinkHop'],
    ['/help', 'Shorten a link'],
    ['/faq', 'Is it really free?'],
    ['/contact', 'https://github.com/navi-crwn/LinkHop/issues/new/choose'],
    ['/terms', 'hel.ink/terms'],
    ['/privacy', 'keyed hash'],
    ['/docs', '/api/shorten'],
]);

test('the status page answers live and is never cached', function () {
    $this->get('/status')->assertOk()
        ->assertSee('LinkHop is up.')
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('the contact page offers a private security report', function () {
    $this->get('/contact')->assertSee('/security/advisories/new', false);
});

test('marketing menus link to LinkHop pages, not to the hub', function () {
    config()->set('hop.hub_url', 'https://hub.example.test');
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain(route('pages.features'))
        ->toContain(route('pages.help'))
        ->toContain(route('pages.faq'))
        ->toContain(route('pages.privacy'));
    foreach (['products', 'help', 'faq', 'about', 'contact', 'privacy', 'terms', 'plans'] as $hubPage) {
        expect($html)->not->toContain('https://hub.example.test/'.$hubPage.'"');
    }
});

test('page names cannot be taken as custom slugs', function () {
    foreach (PageController::PAGES as $page) {
        expect(config('shortener.reserved_slugs'))->toContain($page);
    }
    expect(config('shortener.reserved_slugs'))->toContain('status');
});
