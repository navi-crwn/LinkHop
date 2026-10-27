<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $this->get('/forgot-password')->assertOk()->assertDontSee('catchphrase');
});

test('reset password link can be requested', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('unknown emails get the same response', function () {
    $this->post('/forgot-password', ['email' => 'nobody@example.com'])
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();
});

test('password can be reset with valid token', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        return Hash::check('new-password-123', $user->fresh()->password);
    });
});

test('the reset link is bound to one address and signs out whoever is signed in', function () {
    Notification::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $other) {
        // Opened while signed in as another account: that session ends and the form shows.
        $this->actingAs($other)->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))
            ->assertOk()->assertSee('readonly', false)->assertSee($user->email);
        $this->assertGuest();
        // The token does not work for a different address.
        $this->post('/reset-password', [
            'token' => $notification->token, 'email' => $other->email,
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('new-password-123', $other->fresh()->password));

        return true;
    });
});

test('the reset mail speaks HOP ID', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post('/forgot-password', ['email' => $user->email]);
    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $mail = $notification->toMail($user);

        return $mail->subject === 'Reset your HOP ID password' && str_contains($mail->actionUrl, '/reset-password/'.$notification->token);
    });
});
