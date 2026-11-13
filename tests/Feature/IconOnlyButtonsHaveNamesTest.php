<?php

use App\Models\User;

/*
| On a phone the main action of a page shows only its icon: the text is hidden from everyone, so the name comes from an
| attribute, in the language of the page.
*/

it('names the main action of a page when only its icon shows', function (string $url, string $label) {
    $user = User::factory()->create();
    foreach (['en', 'de'] as $lang) {
        $html = $this->actingAs($user)->get("{$url}?lang={$lang}")->assertOk()->getContent();
        $name = e(trans($label, [], $lang));
        expect($html)->toContain('aria-label="'.$name.'"');
    }
})->with([['/dashboard', 'Create'], ['/links', 'Create'], ['/analytics', 'Create'], ['/settings', 'Create'], ['/folders', 'New folder'], ['/tags', 'New tag']]);

it('names the main action of the bio pages', function () {
    $html = $this->actingAs(User::factory()->create())->get('/bio')->assertOk()->getContent();
    preg_match('~<a href="[^"]*/bio/create"[^>]*>~', $html, $m);
    expect($m[0] ?? '')->toContain('aria-label=');
})->group('bio');
