<?php

use App\Models\User;

it('sends a guest on link.hel.ink/ to the hub sign-in and a member to the dashboard', function () {
    $this->get('/')->assertRedirect(route('hop.sso.start'));
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
});
