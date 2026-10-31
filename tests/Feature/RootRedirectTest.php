<?php

test('GET /l and /l/ redirect 301 to link.hel.ink preserving query parameters', function () {
    $this->get('/l')
        ->assertStatus(301)
        ->assertRedirect('https://link.hel.ink/');

    $this->get('/l/')
        ->assertStatus(301)
        ->assertRedirect('https://link.hel.ink/');

    $this->get('/l/?ref=promo&source=hop')
        ->assertStatus(301)
        ->assertRedirect('https://link.hel.ink/?ref=promo&source=hop');
});
