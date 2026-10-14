<?php

use App\Models\User;

// Found by the synthetic-user suite: every unnamed `throttle:N,M` shared one counter
// per IP, so using the link checker (or the browser sending CSP reports) locked
// a visitor out of logging in.
test('link checks and csp reports do not use up the login limit', function () {
    $user = User::factory()->create();
    foreach (range(1, 6) as $i) {
        $this->get('/check?url=x'.$i);
        $this->postJson('/csp-report', ['csp-report' => []]);
    }
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('login is still limited to 5 attempts a minute', function () {
    $user = User::factory()->create();
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'.$i]);
    }
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
});
