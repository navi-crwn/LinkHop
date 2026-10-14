<?php

test('homepage follows the saved or system theme and offers a toggle', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee("localStorage.getItem('helink-theme')", false)
        ->assertSee('aria-label="Toggle theme"', false)
        ->assertDontSee('<html lang="en" class="h-full dark">', false)
        ->assertSee('id="target_url"', false);
});

test('other marketing pages keep the dark glass look', function () {
    $this->get('/faq')
        ->assertOk()
        ->assertSee('class="h-full dark"', false)
        ->assertDontSee('aria-label="Toggle theme"', false);
});
