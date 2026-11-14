<?php

use App\Models\User;

/*
| Dashboard polish (the browser checks live in tests/e2e/specs/dashboard-polish.spec.ts; these are the server side):
| the family row is part of the sidebar and shown once, and a signed-in member who opens a public page (info page,
| changelog, the public QR tool, the link checker) is taken back to the dashboard, while a visitor goes to the front
| page.
*/

test('the family row is the last thing in the dashboard sidebar and nowhere else on the page', function () {
    $html = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->getContent();

    expect(substr_count($html, 'class="hop-family-row"'))->toBe(1)
        ->and($html)->not->toContain('hop-family-footer');
    $aside = substr($html, strpos($html, '<aside'), strpos($html, '</aside>') - strpos($html, '<aside'));
    expect($aside)->toContain('hop-family-row')->toContain('Hop Easy Link');
    // after "Changelog · GitHub", at the very end of the sidebar
    expect(strrpos($aside, 'hop-family-row'))->toBeGreaterThan(strrpos($aside, 'github.com/navi-crwn/LinkHop'));
});

test('the public pages keep their own family row', function () {
    $this->get('/about')->assertOk()->assertSee('Hop Easy Link');
});

test('a member on a public page is sent back to the dashboard by the logo and the back link, a visitor to the front page', function (string $path) {
    $member = $this->actingAs(User::factory()->create())->get($path)->assertOk()->getContent();
    expect($member)->toMatch('~<a href="'.preg_quote(route('dashboard'), '~').'"[^>]*data-logo~');
    if (str_contains($member, 'Go to dashboard')) {
        expect($member)->not->toContain('Back to LinkHop');
    }

    auth()->logout();
    $guest = $this->flushSession()->get($path)->assertOk()->getContent();
    expect($guest)->toMatch('~<a href="'.preg_quote(route('home'), '~').'"[^>]*data-logo~')
        ->and($guest)->not->toContain('Go to dashboard');
})->with(['/about', '/changelog', '/qr', '/check']);

test('the back link on an information page says where it goes', function () {
    $member = $this->actingAs(User::factory()->create())->get('/about')->assertOk()->getContent();
    expect($member)->toMatch('~<a href="'.preg_quote(route('dashboard'), '~').'" class="inline-flex[^>]*>\s*<span aria-hidden="true">&larr;</span> Go to dashboard~');

    auth()->logout();
    $guest = $this->flushSession()->get('/about')->assertOk()->getContent();
    expect($guest)->toMatch('~<a href="'.preg_quote(route('home'), '~').'" class="inline-flex[^>]*>\s*<span aria-hidden="true">&larr;</span> Back to LinkHop~');
});

test('on a phone the main action of the public top bar is in the menu, not in the bar', function () {
    $guest = $this->get('/about')->assertOk()->getContent();
    expect($guest)->toContain('max-sm:hidden rounded-xl bg-slate-900')->toContain('sm:hidden dark:bg-white dark:text-slate-900');
    $member = $this->actingAs(User::factory()->create())->get('/about')->assertOk()->getContent();
    expect($member)->toContain('max-sm:hidden rounded-xl bg-slate-900');
});

test('the sidebar footer lists Help, Terms, Privacy, Changelog, GitHub and Announcement in that order, then the family row', function () {
    $html = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->getContent();
    $aside = substr($html, strpos($html, '<aside'), strpos($html, '</aside>') - strpos($html, '<aside'));

    $order = [route('pages.help'), route('pages.terms'), route('pages.privacy'), route('changelog'), 'https://github.com/navi-crwn/LinkHop', 'https://hel.ink/announcements', 'class="hop-family-row"'];
    $last = 0;
    foreach ($order as $needle) {
        $at = strpos($aside, 'href="'.$needle.'"') ?: strpos($aside, $needle, $last);
        expect($at)->toBeGreaterThan($last, $needle);
        $last = $at;
    }
    // one entry per line, and nothing else in the footer (the old "Source" and "Licenses" never lived here)
    $footer = substr($aside, strpos($aside, 'data-sidebar-footer'));
    expect(substr_count($footer, '<li'))->toBe(6);
});

test('the sidebar footer is in every language', function () {
    foreach (['en', 'zh-CN', 'de', 'id', 'ja', 'pt-BR', 'es', 'fr', 'ru'] as $lang) {
        $html = $this->actingAs(User::factory()->create())->withUnencryptedCookie('hop_lang', $lang)->get('/dashboard')->assertOk()->getContent();
        $footer = substr($html, strpos($html, 'data-sidebar-footer'));
        foreach (['Help', 'Terms', 'Privacy', 'Changelog', 'Announcement'] as $key) {
            expect($footer)->toContain('>'.e(trans($key, [], $lang)).'</a>');
        }
        if ($lang !== 'en') {
            expect(trans('Announcement', [], $lang))->not->toBe('Announcement');
        }
    }
});
