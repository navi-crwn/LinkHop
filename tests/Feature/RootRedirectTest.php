<?php

test('GET /l and /l/ redirect 301 to the app address (link.hel.ink in production) preserving query parameters', function () {
    config(['app.url' => 'https://link.hel.ink']);

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
