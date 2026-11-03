<?php

use App\Http\Middleware\VerifyHubRequest;
use App\Models\Link;
use App\Models\User;
use App\Support\HopSignature;
use Hop\Auth\Ticket;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
| OP-03 option B: when someone signs out at the hub (or an admin ends their sessions), the hub
| queues a signed session.revoked to every app, so the sign-out does not depend on the browser
| reaching each app. LinkHop signs people in with remember:true, so deleting the session rows is
| not enough: the remember cookie (400 days) must stop working too (audit A2-S5-10).
*/
const REVOKE_SECRET = 'a-shared-sso-secret-that-is-long-enough-1234';

/** A request signed the way the hub's SendHopEvent job signs it (HOP App Protocol hop1), written out here on purpose. */
function hubEvent(array $event, ?string $nonce = null, ?int $ts = null)
{
    $body = json_encode($event, JSON_UNESCAPED_SLASHES);
    $ts = (string) ($ts ?? time());
    $nonce ??= HopSignature::b64(random_bytes(16));
    $sig = HopSignature::b64(hash_hmac('sha256', implode('|', ['hop1', 'helink', $ts, $nonce, 'POST', '/hop/events', hash('sha256', $body)]), REVOKE_SECRET, true));

    return test()->call('POST', '/hop/events', [], [], [], [
        'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_X_HOP_APP' => 'helink', 'HTTP_X_HOP_TIMESTAMP' => $ts,
        'HTTP_X_HOP_NONCE' => $nonce, 'HTTP_X_HOP_ACTOR' => 'system', 'HTTP_X_HOP_SIGNATURE' => $sig,
    ], $body);
}

function revokedEvent(int $userId, string $id = 'evt-revoke-1'): array
{
    return ['id' => $id, 'type' => 'session.revoked', 'at' => now()->toIso8601String(), 'data' => ['sub' => (string) $userId, 'hop_id' => (string) $userId]];
}

/** Signs in through the real SSO callback (which uses remember:true) and returns the remember cookie as the browser would hold it. */
function signInAndKeepRememberCookie(User $user): array
{
    $state = str_repeat('r', 40);
    $payload = ['sub' => (string) $user->id, 'aud' => 'linkhop', 'state' => $state, 'iat' => time(), 'exp' => time() + 120, 'jti' => bin2hex(random_bytes(8)), 'status' => $user->status];
    $body = Ticket::b64(json_encode($payload));
    $ticket = $body.'.'.Ticket::b64(hash_hmac('sha256', $body, REVOKE_SECRET, true));
    $response = test()->withSession(['hop_sso' => ['state' => $state, 'next' => '/dashboard']])->get('/auth/sso/callback?ticket='.$ticket);
    $response->assertRedirect('/dashboard');
    $name = 'remember_web_'.sha1(SessionGuard::class);
    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === $name);
    expect($cookie)->not->toBeNull();

    return [$name, $cookie->getValue()];
}

/** A different browser: no session, only the remember cookie it kept. */
function browserWithOnlyRememberCookie(string $name, string $value)
{
    app('auth')->forgetGuards();
    test()->flushSession();

    return test()->withUnencryptedCookie($name, $value);
}

beforeEach(function () {
    config(['hop-auth.secret' => REVOKE_SECRET, 'hop-auth.app' => 'linkhop', 'hop-auth.hub_url' => 'https://hub.example.test', 'hop.hub_url' => 'https://hub.example.test']);
    Cache::flush();
});

test('a person who is signed in only by the remember cookie stays signed in after the hub revokes their sessions: the bug', function () {
    $user = User::factory()->create();
    [$name, $value] = signInAndKeepRememberCookie($user);
    browserWithOnlyRememberCookie($name, $value)->get('/dashboard')->assertOk(); // control: the cookie works before the revoke

    hubEvent(revokedEvent($user->id))->assertOk();

    // After the fix the cookie is dead: the guest is sent to the hub's sign-in instead of the dashboard.
    browserWithOnlyRememberCookie($name, $value)->get('/dashboard')->assertRedirect();
    app('auth')->forgetGuards();
    $this->assertGuest();
});

test('session.revoked deletes every session row of that person and nobody else’s', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $other = User::factory()->create();
    foreach ([['a', $user->id], ['b', $user->id], ['c', $other->id], ['d', null]] as [$id, $uid]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $uid, 'ip_address' => '127.0.0.1', 'user_agent' => 't', 'payload' => 'x', 'last_activity' => time()]);
    }

    hubEvent(revokedEvent($user->id))->assertOk()->assertJsonPath('data.handled', true);

    // (the event request itself opens a session row of its own: look only at the four seeded ones)
    expect(DB::table('sessions')->pluck('id')->intersect(['a', 'b', 'c', 'd'])->sort()->values()->all())->toBe(['c', 'd']);
});

test('the revoke changes the remember token of that person only', function () {
    $user = User::factory()->create(['remember_token' => 'token-of-the-person']);
    $other = User::factory()->create(['remember_token' => 'token-of-somebody-else']);

    hubEvent(revokedEvent($user->id))->assertOk();

    expect($user->fresh()->remember_token)->not->toBe('token-of-the-person')
        ->and($other->fresh()->remember_token)->toBe('token-of-somebody-else');
});

test('an event the hub retries is acknowledged and not run twice, a replayed request is refused, a bad signature is refused', function () {
    $user = User::factory()->create(['remember_token' => 'first']);
    $event = revokedEvent($user->id, 'evt-retry-1');

    hubEvent($event, nonce: 'nonce-number-one-abcdef')->assertOk()->assertJsonPath('data.handled', true);
    $token = $user->fresh()->remember_token;
    hubEvent($event, nonce: 'nonce-number-two-abcdef')->assertOk()->assertJsonPath('data.duplicate', true);
    hubEvent($event, nonce: 'nonce-number-one-abcdef')->assertStatus(401); // same nonce: replay
    test()->call('POST', '/hop/events', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($event))->assertStatus(401);

    expect($user->fresh()->remember_token)->toBe($token);
});

test('a revoke signed with the old hex scheme still gets through the existing hub.signed check', function () {
    $user = User::factory()->create(['remember_token' => 'old-token']);
    $ts = time();
    $json = json_encode(revokedEvent($user->id, 'evt-hex-1'));
    $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_X_HOP_TIMESTAMP' => (string) $ts, 'HTTP_X_HOP_SIGNATURE' => VerifyHubRequest::sign(REVOKE_SECRET, $ts, 'POST', '/hop/events', $json)];

    $this->call('POST', '/hop/events', [], [], [], $server, $json)->assertOk();

    expect($user->fresh()->remember_token)->not->toBe('old-token');
});

test('account.deleted still purges the account’s data and now also ends its sessions', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'gone01', 'target_url' => 'https://example.com/g', 'status' => 'active']);
    DB::table('sessions')->insert(['id' => 'del', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 't', 'payload' => 'x', 'last_activity' => time()]);

    hubEvent(['id' => 'evt-del-1', 'type' => 'account.deleted', 'data' => ['sub' => (string) $user->id, 'hop_id' => (string) $user->id, 'email' => $user->email]])->assertOk();

    expect(Link::whereKey($link->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('account.status suspended keeps the existing suspend behaviour: the next request goes to the hub’s notice, and the owner’s links stop at once', function () {
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'susp01', 'target_url' => 'https://example.com/s', 'status' => 'active']);
    $this->get('/l/susp01')->assertRedirect('https://example.com/s'); // now cached for five minutes, with the owner as they were

    $user->forceFill(['status' => User::STATUS_SUSPENDED])->save(); // the hub writes this to the shared table
    hubEvent(['id' => 'evt-status-1', 'type' => 'account.status', 'data' => ['sub' => (string) $user->id, 'hop_id' => (string) $user->id, 'status' => 'suspended']])->assertOk();

    $this->get('/l/susp01')->assertNotFound();
    $this->actingAs($user)->get('/dashboard')->assertRedirect('https://hub.example.test/suspended');
});

test('events this app does not know are acknowledged with 200', function () {
    hubEvent(['id' => 'evt-new-1', 'type' => 'something.new', 'data' => []])->assertOk()->assertJsonPath('data.handled', false);
    hubEvent(['id' => 'evt-prof-1', 'type' => 'profile.updated', 'data' => ['sub' => '1']])->assertOk();
});
