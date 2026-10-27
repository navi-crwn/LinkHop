<?php

use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

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
test('sign-up emails are sent after the response, not before it', function () {
    Mail::fake();
    Notification::fake();
    $deferred = app(DeferredCallbackCollection::class);
    $this->withoutMiddleware(InvokeDeferredCallbacks::class)->post('/register', [
        'name' => 'Slow Mail', 'email' => 'slow@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'tos_accepted' => '1',
    ]);
    Notification::assertNothingSent();
    $deferred->invoke();
    $user = User::where('email', 'slow@example.com')->first();
    Notification::assertSentTo($user, VerifyEmail::class);
    Mail::assertSent(WelcomeEmail::class);
});
