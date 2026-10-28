<?php

test('homepage follows the saved or system theme and offers a toggle', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee("localStorage.getItem('helink-theme')", false)
        ->assertSee('aria-label="Toggle theme"', false)
        ->assertDontSee('<html lang="en" class="h-full dark">', false)
        ->assertSee('id="target_url"', false);
});
