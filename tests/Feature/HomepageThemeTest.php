<?php

test('homepage follows the saved or system theme and offers a toggle', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee("localStorage.getItem('helink-theme')", false)
        ->assertSee('aria-label="Toggle theme"', false)
        ->assertDontSee('<html lang="en" class="h-full dark">', false)
        ->assertSee('id="target_url"', false);
});

test('home page translations cover every rendered string', function (string $locale) {
    $missing = [];
    app('translator')->handleMissingKeysUsing(function ($key) use (&$missing) {
        if (! in_array($key, ['HOP ID', 'BioHop', 'PixelHop', 'NoteHop'], true)) {
            $missing[] = $key;
        }

        return $key;
    });
    $this->get('/?lang='.$locale)->assertOk();
    expect(array_values(array_unique($missing)))->toBe([], implode(', ', array_unique($missing)));
})->with(['en', 'id', 'zh', 'es', 'ar', 'pt', 'fr', 'ja']);
