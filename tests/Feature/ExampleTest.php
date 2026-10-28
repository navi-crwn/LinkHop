<?php

use App\Models\User;

it('shows LinkHop\'s landing to a guest on link.hel.ink/ and sends a member to the dashboard', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('id="target_url"', false)
        ->assertSee('hop-hero', false);
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
});
