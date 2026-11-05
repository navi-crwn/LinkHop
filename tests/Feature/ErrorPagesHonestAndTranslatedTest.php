<?php

use App\Models\User;
use Hop\Bio\Models\BioPage;
use Hop\Ui\Locales;

/*
| The 500 page says only what is true: nothing reports an error to a team. The not-found page of a bio page speaks the
| visitor's language.
*/

it('does not claim on the 500 page that the team was notified, in any language', function (string $lang) {
    app()->setLocale($lang);
    $html = view('errors.500')->render();
    $catalog = json_decode((string) file_get_contents(lang_path("{$lang}.json")), true);
    expect($html)->not->toContain('notified')
        ->toContain(e($catalog['Try again in a moment. If it keeps happening, send a message to the address below.']))
        ->and(array_key_exists("Our team has been notified and we're working on fixing this issue.", $catalog))->toBeFalse();
})->with(fn () => Locales::codes());

it('answers an unknown bio page in the language of the visitor', function (string $lang) {
    $catalog = json_decode((string) file_get_contents(lang_path("{$lang}.json")), true);
    $html = $this->get("/b/nosuchslugxx?lang={$lang}")->assertNotFound()->getContent();
    expect($html)->toContain('<html lang="'.$lang.'"')
        ->toContain('<title>'.e($catalog['Page not found']).' - LinkHop</title>')
        ->toContain(e($catalog['Create Your Bio Page']))
        ->toContain('nosuchslugxx');
    if ($lang !== 'en') {
        expect($html)->not->toContain('Create Your Bio Page')->not->toContain('Looking for something specific?');
    }
})->with(fn () => Locales::codes())->group('bio');

it('answers an unpublished bio page in the language of the visitor', function () {
    $user = User::factory()->create();
    BioPage::create(['user_id' => $user->id, 'slug' => 'hiddenpage', 'title' => 'x', 'theme' => 'default', 'is_published' => false]);
    $catalog = json_decode((string) file_get_contents(lang_path('de.json')), true);
    $html = $this->get('/b/hiddenpage?lang=de')->assertNotFound()->getContent();
    expect($html)->toContain('<html lang="de"');
    if (str_contains($html, e($catalog['Page Not Available']))) {
        expect($html)->toContain(e($catalog['This bio page is currently not published. The owner may have temporarily disabled it.']));
    }
})->group('bio');

it('keeps a slug harmless in the translated sentence', function () {
    $html = $this->get('/b/%3Cimg%3E')->getContent();
    expect($html)->not->toContain('<img>');
})->group('bio');
