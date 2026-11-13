<?php

test('homepage follows the family theme cookie, the old saved theme or the system, and offers a toggle', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('hop_theme=', false)
        ->assertSee('data-old-key="helink-theme"', false)
        ->assertSee('localStorage.getItem(oldKey)', false)
        ->assertSee('aria-label="Toggle theme"', false)
        ->assertDontSee('<html lang="en" class="h-full dark">', false)
        ->assertSee('id="target_url"', false);
});

test('home page translations cover every rendered string', function (string $locale) {
    $missing = [];
    app('translator')->handleMissingKeysUsing(function ($key) use (&$missing) {
        if (! in_array($key, ['HOP ID', 'BioHop', 'PixelHop', 'NoteHop', 'FileHop', 'PayHop'], true)) {
            $missing[] = $key;
        }

        return $key;
    });
    $this->get('/?lang='.$locale)->assertOk();
    expect(array_values(array_unique($missing)))->toBe([], implode(', ', array_unique($missing)));
})->with(['en', 'id', 'zh-CN', 'de', 'es', 'pt-BR', 'fr', 'ja', 'ru']);
