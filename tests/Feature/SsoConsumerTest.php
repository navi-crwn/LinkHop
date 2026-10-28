<?php

use App\Models\User;
use Hop\Auth\Ticket;
use Illuminate\Support\Facades\Cache;

/*
| LinkHop as a HOP ID consumer: no accounts of its own. /login starts the hub's
| SSO (hop/auth), the callback signs the same user in from the shared users
| table, /logout ends the session here and at the hub.
*/
const SSO_SECRET = 'a-shared-sso-secret-that-is-long-enough-1234';

function ssoTicket(User $user, string $state, array $extra = []): string
{
    $payload = ['sub' => (string) $user->id, 'aud' => 'linkhop', 'state' => $state, 'iat' => time(), 'exp' => time() + 120, 'jti' => bin2hex(random_bytes(8)), 'status' => $user->status];
    $payload = $extra + $payload;
    $body = Ticket::b64(json_encode($payload));

    return $body.'.'.Ticket::b64(hash_hmac('sha256', $body, SSO_SECRET, true));
}

beforeEach(function () {
    config(['hop-auth.secret' => SSO_SECRET, 'hop-auth.app' => 'linkhop', 'hop-auth.hub_url' => 'https://hub.example.test', 'hop.hub_url' => 'https://hub.example.test',
        'hop.apps.pixelhop.url' => 'https://p.hel.ink', 'hop.apps.biohop.url' => 'https://bio.hel.ink']);
});

test('the local sign-in, sign-up, password and profile pages are gone', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/register/x', '/forgot-password', '/reset-password/abc', '/verify-email', '/auth/google', '/two-factor-challenge', '/onboarding', '/sso/pixelhop', '/api/pixelhop/login']);

test('/login and a protected page send a guest through the hub and back to where they were going', function () {
    $this->get('/links')->assertRedirect('/auth/sso?next=%2Flinks');
    $this->get('/login?next=/analytics')->assertRedirect(route('hop.sso.start', ['next' => '/analytics']));
    $this->get('/register')->assertRedirect('https://hub.example.test/register');

    $start = $this->get('/auth/sso?next=/analytics');
    $start->assertRedirect();
    $location = $start->headers->get('Location');
    expect($location)->toStartWith('https://hub.example.test/sso/linkhop?state=')
        ->and($location)->toContain('return_to='.rawurlencode(url('/auth/sso/callback')));
});

test('a valid ticket from the hub signs the shared user in here', function () {
    $user = User::factory()->create();
    $state = str_repeat('s', 40);

    $this->withSession(['hop_sso' => ['state' => $state, 'next' => '/analytics']])
        ->get('/auth/sso/callback?ticket='.ssoTicket($user, $state))
        ->assertRedirect('/analytics');
    $this->assertAuthenticatedAs($user);
    $this->get('/dashboard')->assertOk();
});

test('a ticket with the wrong signature, state or audience is refused', function () {
    $user = User::factory()->create();
    $state = str_repeat('s', 40);

    $pending = fn () => $this->flushSession()->withSession(['hop_sso' => ['state' => $state, 'next' => '']]);
    $pending()->get('/auth/sso/callback?ticket='.ssoTicket($user, 'other-state'))->assertForbidden();
    $pending()->get('/auth/sso/callback?ticket='.ssoTicket($user, $state, ['aud' => 'biohop']))->assertForbidden();
    $bad = ssoTicket($user, $state);
    $pending()->get('/auth/sso/callback?ticket='.substr($bad, 0, -4).'AAAA')->assertForbidden();
    $this->assertGuest();
});

test('a suspended account is refused at the callback and signed out on the next request', function () {
    $user = User::factory()->create();
    $state = str_repeat('s', 40);
    $this->withSession(['hop_sso' => ['state' => $state, 'next' => '']])->get('/auth/sso/callback?ticket='.ssoTicket($user, $state))->assertRedirect('/dashboard');

    $user->forceFill(['status' => User::STATUS_SUSPENDED])->save();
    $this->app['auth']->forgetGuards(); // the next request loads the user afresh, as production does
    $this->get('/dashboard')->assertRedirect('https://hub.example.test/suspended');
    $this->assertGuest();

    Cache::flush();
    $this->withSession(['hop_sso' => ['state' => $state, 'next' => '']])->get('/auth/sso/callback?ticket='.ssoTicket($user->fresh(), $state))->assertForbidden();
});

test('/logout ends the session here and goes on to the hub; the hub chain uses /auth/sso/logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('https://hub.example.test/logout');
    $this->assertGuest();

    $this->actingAs($user)->get('/auth/sso/logout?next=https://hub.example.test/dashboard')->assertRedirect('https://hub.example.test/dashboard');
    $this->assertGuest();
    $this->actingAs($user)->get('/auth/sso/logout?next=https://evil.example/')->assertRedirect('/');
});

test('the dashboard shows the HOP app switcher and links the account pages to the hub', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk()
        ->assertSee('aria-label="HOP apps"', false)
        ->assertSee('https://hub.example.test/profile')
        ->assertSee('BioHop')->assertSee('PixelHop');
    $this->actingAs($user)->get('/profile')->assertRedirect('https://hub.example.test/profile');
    $this->actingAs($user)->get('/settings')->assertOk()->assertSee('https://hub.example.test/settings')->assertDontSee('Two-Factor Authentication');
});
