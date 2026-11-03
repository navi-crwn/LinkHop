<?php

use App\Models\User;

it('does not let the browser keep a signed-in LinkHop page (Back after logout)', function () {
    $user = User::factory()->create();
    $res = $this->actingAs($user)->get('/dashboard');
    $res->assertOk();
    expect($res->headers->get('Cache-Control'))->toContain('no-store');
});
