<?php

use App\Models\User;

/** The built-in admin pages are gone: /admin/* points admins at the HOP control center (Imperium). */
test('/admin sends an admin to the control center when HOP_CONTROL_URL is set', function (string $path) {
    config(['hop.control_url' => 'https://imperium.example.test']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get($path)->assertRedirect('https://imperium.example.test');
})->with(['/admin', '/admin/users', '/admin/abuse/flags/1/restore', '/admin/bio/monitor']);

test('/admin explains where the admin panel went when no control center is configured', function () {
    config(['hop.control_url' => null, 'hop.control_name' => 'Imperium']);
    $admin = User::factory()->owner()->create();

    $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('The admin panel moved')->assertSee('Imperium')->assertSee('HOP_CONTROL_URL');
    $this->actingAs($admin)->get('/admin/links?q=x')->assertOk()->assertSee('The admin panel moved');
});

test('/admin is forbidden for a normal user', function () {
    config(['hop.control_url' => 'https://imperium.example.test']);
    $member = User::factory()->create();

    $this->actingAs($member)->get('/admin')->assertForbidden();
    $this->actingAs($member)->get('/admin/users')->assertForbidden();
});

test('/admin asks a guest to sign in', function () {
    config(['hop.control_url' => 'https://imperium.example.test']);

    // hop.auth: a guest is sent through the hub's SSO and comes back to /admin.
    $this->get('/admin')->assertRedirect(route('hop.sso.start', ['next' => '/admin']));
});

test('admins see the control center in the sidebar and a banner on the dashboard, members do not', function () {
    config(['hop.control_url' => 'https://imperium.example.test', 'hop.control_name' => 'Imperium']);
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    $this->actingAs($admin)->get('/dashboard')->assertOk()
        ->assertSee('https://imperium.example.test')->assertSee('Open Imperium')->assertDontSee('Admin Control Panel');
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertDontSee('imperium.example.test');

    config(['hop.control_url' => null]);
    $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Where did the admin panel go?')->assertDontSee('imperium.example.test');
});
