<?php

use App\Models\User;

it('shows LinkHop\'s landing to a guest on link.hel.ink/ and sends a member to the dashboard', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('id="target_url"', false)
        ->assertSee('hop-hero', false);
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
});

it('keeps the landing about LinkHop and points to BioHop as a family app', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('id="bio"', false)
        ->assertDontSee('All your links on one page.')
        ->assertSee('BioHop');
});

it('lets the logout chain leave for every HOP host (form-action covers the redirects)', function () {
    config([
        'hop.hub_url' => 'https://hub.hel.ink',
        'shortener.public_url' => 'https://hel.ink',
        'hop.apps.linkhop.url' => 'https://link.hel.ink',
        'hop.apps.biohop.url' => 'https://bio.hel.ink',
        'hop.apps.pixelhop.url' => 'https://p.hel.ink',
    ]);
    $csp = $this->get('/')->headers->get('Content-Security-Policy');
    preg_match('/form-action ([^;]+)/', $csp, $m);
    foreach (['https://hub.hel.ink', 'https://hel.ink', 'https://link.hel.ink', 'https://bio.hel.ink', 'https://p.hel.ink'] as $origin) {
        expect($m[1])->toContain($origin);
    }
});
