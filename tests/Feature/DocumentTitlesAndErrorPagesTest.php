<?php

use App\Models\User;
use Hop\Bio\Models\BioPage;
use Hop\Ui\Locales;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
| The <title> of every page follows the page language and is built as "Page · LinkHop", and the 404, 429, 500 and
| "link not found" pages are translated and answer in the language of the cookie or ?lang=, with no database.
*/

/** The text between <title> and </title>, entities decoded. */
function dtTitle(string $html): string
{
    preg_match('~<title>(.*?)</title>~s', $html, $m);

    return html_entity_decode(trim($m[1] ?? ''), ENT_QUOTES);
}

it('puts a translated "Page · LinkHop" title on the public pages in every language', function (string $path, string $key) {
    foreach (Locales::codes() as $lang) {
        $this->flushSession();
        $html = $this->get($path.'?lang='.$lang)->assertOk()->getContent();
        expect(dtTitle($html))->toBe(trans($key, [], $lang).' · LinkHop', "{$path} in {$lang}");
        // the social title follows the page title
        expect($html)->toContain('<meta property="og:title" content="'.e(trans($key, [], $lang).' · LinkHop').'">');
    }
})->with([
    ['/features', 'Features'], ['/faq', 'FAQ'], ['/terms', 'Terms'], ['/privacy', 'Privacy'], ['/docs', 'API'],
    ['/help', 'Help'], ['/status', 'Status'], ['/about', 'About LinkHop'], ['/contact', 'Contact'], ['/changelog', 'Changelog'],
]);

it('separates the page from the app name with a dot on the other public pages too', function () {
    foreach (Locales::codes() as $lang) {
        expect(dtTitle($this->get('/check?lang='.$lang)->assertOk()->getContent()))->toBe(trans('Check a link', [], $lang).' · LinkHop')
            ->and(dtTitle($this->get('/report-abuse?lang='.$lang)->assertOk()->getContent()))->toBe(trans('Report abuse', [], $lang).' · LinkHop');
    }
});

it('puts a translated "Page · LinkHop" title on the signed-in pages', function (string $path, string $key) {
    $this->actingAs(User::factory()->create());
    foreach (['en', 'de', 'ja', 'ru'] as $lang) {
        $title = dtTitle($this->get($path.'?lang='.$lang)->assertOk()->getContent());
        expect($title)->toBe(trans($key, [], $lang).' · LinkHop', "{$path} in {$lang}")->not->toContain(' - ');
    }
})->with([
    ['/dashboard', 'Dashboard'], ['/links', 'My links'], ['/links/create', 'New link'], ['/links/import', 'Import links'],
    ['/analytics', 'Analytics'], ['/folders', 'Folders'], ['/tags', 'Tags'], ['/settings', 'Settings'],
    ['/api-docs', 'API'],
]);

it('puts a translated "Page · LinkHop" title on the bio pages', function (string $path, string $key) {
    $this->actingAs(User::factory()->create());
    foreach (['en', 'de', 'ja', 'ru'] as $lang) {
        expect(dtTitle($this->get($path.'?lang='.$lang)->assertOk()->getContent()))->toBe(trans($key, [], $lang).' · LinkHop', "{$path} in {$lang}");
    }
})->with([['/bio', 'Link in Bio'], ['/bio/create', 'New bio page']])->group('bio');

it('titles the bio editor and the bio analytics page after what they are', function () {
    $user = User::factory()->create();
    $page = BioPage::create(['user_id' => $user->id, 'slug' => 'titlebio', 'title' => 'Demo page', 'is_published' => true]);
    $this->actingAs($user);

    expect(dtTitle($this->get(route('bio.edit', $page).'?lang=de')->assertOk()->getContent()))->toBe('Bio-Seite bearbeiten: Demo page · LinkHop')
        ->and(dtTitle($this->get(route('bio.analytics', $page).'?lang=de')->assertOk()->getContent()))->toBe('Statistiken: Demo page · LinkHop');
})->group('bio');

it('answers an unknown address with a 404 page in the language of ?lang= and of the cookie', function () {
    foreach (Locales::codes() as $lang) {
        $viaQuery = $this->get('/no/such/deep/page?lang='.$lang)->assertNotFound();
        // A missing dashboard page is not a missing short link: the page says "page", not "short link".
        expect($viaQuery->getContent())->toContain('<html lang="'.$lang.'"')
            ->toContain('>'.e(trans('Page not found', [], $lang)).'</h1>')
            ->not->toContain(e(trans('Link not found', [], $lang)));
        $viaCookie = $this->withUnencryptedCookie('hop_lang', $lang)->get('/no/such/deep/page')->assertNotFound();
        expect($viaCookie->getContent())->toContain('<html lang="'.$lang.'"');
    }
});

it('renders the 429, 500 and link-missing pages in every language', function () {
    foreach (Locales::codes() as $lang) {
        app()->setLocale($lang);
        $e429 = view('errors.429', ['exception' => new HttpException(429, '', null, ['Retry-After' => 30])])->render();
        $e500 = view('errors.500', ['exception' => new RuntimeException('x')])->render();
        $missing = view('errors.link-missing', ['slug' => 'abc'])->render();
        $expired = view('errors.link-missing', ['slug' => 'abc', 'expired' => true])->render();

        expect(dtTitle($e429))->toBe(trans('Too many requests', [], $lang).' · LinkHop')
            ->and($e429)->toContain(e(trans('429: Too many requests', [], $lang)))
            ->and($e429)->toContain('<strong data-retry-seconds>30</strong>')
            ->and(dtTitle($e500))->toBe(trans('Something went wrong', [], $lang).' · '.config('app.name'))
            ->and($e500)->toContain('<html lang="'.$lang.'"')->toContain(e(trans('Go to homepage', [], $lang)))
            ->and(dtTitle($missing))->toBe(trans('Link not found', [], $lang).' · '.config('app.name'))
            ->and($missing)->toContain('<html lang="'.$lang.'"')->toContain(e(trans('What you can do:', [], $lang)))
            ->and(dtTitle($expired))->toBe(trans('Link expired', [], $lang).' · '.config('app.name'))
            ->and($expired)->toContain('<span class="font-semibold text-blue-600 dark:text-blue-400">abc</span>');
    }
});

it('does not print a slug as markup on the link-missing page', function () {
    $html = view('errors.link-missing', ['slug' => '<img src=x onerror=alert(1)>'])->render();

    expect($html)->not->toContain('<img src=x')->toContain('&lt;img src=x');
});

it('has every new error-page text in all nine languages, different from English where it should be', function () {
    $keys = ['Too many requests', 'Something went wrong', 'Link expired', 'Go to dashboard', 'Page not found', 'Reloading…', 'New bio page', 'Edit bio page'];
    foreach (Locales::codes() as $lang) {
        if ($lang === 'en') {
            continue;
        }
        foreach ($keys as $key) {
            expect(trans($key, [], $lang))->not->toBe($key, "{$key} in {$lang}");
        }
    }
});
