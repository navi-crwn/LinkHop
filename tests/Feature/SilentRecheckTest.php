<?php

use App\Models\User;
use Hop\Auth\SilentRecheck;
use Hop\Auth\Ticket;

/*
| The silent account re-check (hop/auth): the test suite turns it off (HOP_RECHECK=false in phpunit.xml) so
| actingAs() stays simple; here it is on. A session the hub has not vouched for lately is confirmed with one
| prompt=none trip, on protected pages and on the public pages that show the account, never on a redirect hot path.
*/
const RECHECK_SECRET = 'a-shared-sso-secret-that-is-long-enough-1234';

beforeEach(function () {
    config(['hop-auth.secret' => RECHECK_SECRET, 'hop-auth.app' => 'linkhop', 'hop-auth.hub_url' => 'https://hub.example.test',
        'hop-auth.recheck' => ['enabled' => true, 'max_age' => 300, 'cooldown' => 30, 'retry_after' => 300, 'probe' => true, 'probe_timeout' => 1.5]]);
    SilentRecheck::$probe = fn () => 200;
});

afterEach(function () {
    SilentRecheck::$probe = null;
});

function recheckTicketFor(User $user, string $state): string
{
    $payload = ['sub' => (string) $user->id, 'aud' => 'linkhop', 'state' => $state, 'iat' => time(), 'exp' => time() + 120, 'jti' => bin2hex(random_bytes(8)), 'status' => $user->status];
    $body = Ticket::b64(json_encode($payload));

    return $body.'.'.Ticket::b64(hash_hmac('sha256', $body, RECHECK_SECRET, true));
}

test('a protected page with a stale session asks the hub silently (prompt=none)', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/links');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://hub.example.test/sso/linkhop?')->toContain('prompt=none');
});

test('the public pages that show the account re-check too, a short link redirect never does', function () {
    $user = User::factory()->create();

    foreach (['/', '/faq', '/changelog', '/qr'] as $path) {
        $location = $this->actingAs($user)->get($path)->assertRedirect()->headers->get('Location');
        expect($location)->toContain('hub.example.test')->toContain('prompt=none');
        session()->flush();
    }
    $this->flushSession();
    $this->actingAs($user)->get('/l/does-not-exist')->assertStatus(404);
});

test('a guest passes untouched', function () {
    $this->get('/')->assertOk();
    $this->get('/faq')->assertOk();
});

test('a fresh session, a form post and a JSON call do not leave the page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(['hop_verified_at' => time()])->get('/links')->assertOk();
    $this->flushSession();
    $this->actingAs($user)->getJson('/links')->assertStatus(200);
});

test('account A in the app, account B at the hub: the silent answer switches the session to B', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    $start = $this->actingAs($a)->get('/dashboard');
    $state = '';
    parse_str((string) parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);
    $state = $query['state'];
    expect($state)->not->toBe('');

    $this->get('/auth/sso/callback?ticket='.recheckTicketFor($b, $state))->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($b);
    expect(session('hop_verified_at'))->toBeGreaterThan(time() - 5);
});

test('login_required from the hub signs the app session out', function () {
    $a = User::factory()->create();
    $start = $this->actingAs($a)->get('/dashboard');
    parse_str((string) parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->get('/auth/sso/callback?error=login_required&state='.$query['state'])->assertRedirect();
    $this->assertGuest();
});

test('a hub that does not answer its health URL does not log anyone out: the session stays trusted', function () {
    SilentRecheck::$probe = fn () => 502;
    Cache::flush();
    $user = User::factory()->create();

    $this->actingAs($user)->get('/links')->assertOk();
    $this->get('/faq')->assertOk();
});
