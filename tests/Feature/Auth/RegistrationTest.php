<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');
    $response->assertStatus(200);
});
test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'tos_accepted' => '1',
    ]);
    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice'));
});
test('admin-looking email domains do not grant a role', function () {
    $this->post('/register', [
        'name' => 'Sneaky',
        'email' => 'boss@superadmin.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'tos_accepted' => '1',
    ]);
    expect(User::where('email', 'boss@superadmin.com')->value('role'))->toBe('user');
});
test('disposable email addresses are rejected', function () {
    $this->post('/register', [
        'name' => 'Spam',
        'email' => 'x@mailinator.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'tos_accepted' => '1',
    ])->assertSessionHasErrors('email');
    $this->assertGuest();
});
test('registration is rate limited per ip', function () {
    config(['abuse.register_per_ip_per_hour' => 2]);
    foreach (range(1, 2) as $i) {
        $this->post('/register', ['name' => 'u', 'email' => "u{$i}@example.com", 'password' => 'password', 'password_confirmation' => 'password', 'tos_accepted' => '1']);
        auth()->logout();
    }
    $this->post('/register', ['name' => 'u', 'email' => 'u3@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'tos_accepted' => '1'])
        ->assertStatus(429);
});
