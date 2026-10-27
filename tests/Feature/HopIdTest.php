<?php

namespace Tests\Feature;

use App\Jobs\SendHopEvent;
use App\Models\HopApp;
use App\Models\Role;
use App\Models\User;
use App\Models\UsernameHistory;
use App\Support\HopSignature;
use App\Support\Username;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** HOP ID: usernames, roles, the signed HOP API, ticket v2 and account events. */
class HopIdTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'notehop-secret-that-is-long-enough-12345';

    // ---- usernames -------------------------------------------------------

    public function test_username_rules(): void
    {
        $this->assertNull(Username::problem('rani.putri'));
        $this->assertNull(Username::problem('a1-b_c'));
        $this->assertNotNull(Username::problem('ab'));
        $this->assertNotNull(Username::problem('Rani'));
        $this->assertNotNull(Username::problem('.rani'));
        $this->assertNotNull(Username::problem('rani.'));
        $this->assertNotNull(Username::problem('ra..ni'));
        $this->assertNotNull(Username::problem('admin'));
        $this->assertNotNull(Username::problem('hop-team'));
        $this->assertNotNull(Username::problem(str_repeat('a', 31)));
        $this->assertSame('rani.putri', Username::suggest('Rani Putri'));
        $this->assertSame('rani', Username::suggest('rani@example.com'));
        User::factory()->create(['username' => 'rani']);
        $this->assertSame('rani2', Username::suggest('rani@example.com'));
        $this->assertNotNull(Username::problem('rani'));
    }

    public function test_sign_up_assigns_a_username_and_settings_can_change_it(): void
    {
        $this->post('/register', ['name' => 'Rani Putri', 'email' => 'rani@example.com', 'password' => 'a-Strong-pass-123',
            'password_confirmation' => 'a-Strong-pass-123', 'tos_accepted' => '1']);
        $u = User::where('email', 'rani@example.com')->firstOrFail();
        $this->assertSame('rani.putri', $u->username);
        $this->actingAs($u)->withSession(['2fa_verified' => true])
            ->patch('/profile', ['name' => 'Rani', 'email' => 'rani@example.com', 'username' => '@Rani_P', 'bio' => 'hi', 'website' => 'rani.example', '_profile_form' => 1])
            ->assertSessionHasNoErrors();
        $u->refresh();
        $this->assertSame('rani_p', $u->username);
        $this->assertSame('https://rani.example', $u->website);
        $this->assertFalse($u->show_owner); // checkbox absent in a profile form = off
        $this->assertSame(2, $u->profile_version);
        // The old name is on hold for others, still free for its owner.
        $this->assertTrue(UsernameHistory::where('username', 'rani.putri')->exists());
        $other = User::factory()->create();
        $this->assertNotNull(Username::problem('rani.putri', $other));
        $this->assertNull(Username::problem('rani.putri', $u));
        $this->actingAs($other)->withSession(['2fa_verified' => true])
            ->patch('/profile', ['name' => 'X', 'email' => $other->email, 'username' => 'rani.putri'])
            ->assertSessionHasErrors('username');
        $this->actingAs($other)->withSession(['2fa_verified' => true])->getJson('/profile/username-check?u=Rani_P')
            ->assertOk()->assertJsonPath('ok', false);
        $this->actingAs($other)->withSession(['2fa_verified' => true])->getJson('/profile/username-check?u=free.name')
            ->assertOk()->assertJsonPath('ok', true);
    }

    // ---- roles -----------------------------------------------------------

    public function test_roles_replace_the_superadmin_email(): void
    {
        config(['auth.superadmin_email' => 'boss@example.com']);
        $legacy = User::factory()->create(['email' => 'boss@example.com']);
        $this->assertTrue($legacy->isOwner());
        $this->assertSame(['owner'], $legacy->roleNames());
        $owner = User::factory()->owner()->create();
        $admin = User::factory()->admin()->create();
        $appAdmin = User::factory()->admin('pixelhop')->create();
        $user = User::factory()->create();
        $this->assertTrue($owner->isAdmin() && $admin->isAdmin());
        $this->assertFalse($admin->isOwner());
        $this->assertFalse($appAdmin->isAdmin());
        $this->assertTrue($appAdmin->isAdminOf('pixelhop'));
        $this->assertFalse($appAdmin->isAdminOf('notehop'));
        $this->assertFalse($user->isAdminOf('pixelhop'));
        $this->assertSame(['admin:pixelhop'], $appAdmin->roleNames());
        $this->actingAs($admin)->withSession(['2fa_verified' => true])->get('/admin/users')->assertOk();
        $this->actingAs($user)->withSession(['2fa_verified' => true])->get('/admin/users')->assertForbidden();
    }

    public function test_admin_pages_need_two_factor_when_enabled(): void
    {
        $admin = User::factory()->owner()->create(['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()]);
        $this->actingAs($admin)->get('/admin/users')->assertRedirect(route('two-factor.show'));
    }

    // ---- ticket v2 -------------------------------------------------------

    public function test_ticket_v2_carries_the_profile_and_roles(): void
    {
        HopApp::factory()->notehop(self::SECRET)->create();
        $u = User::factory()->admin('notehop')->create(['username' => 'rani', 'bio' => 'hello']);
        $res = $this->actingAs($u)->withSession(['2fa_verified' => true])
            ->get('/sso/notehop?'.http_build_query(['state' => 'abcdefghijklmnop1234', 'return_to' => 'https://note.example.com/auth/sso/callback']));
        parse_str((string) parse_url($res->headers->get('Location'), PHP_URL_QUERY), $q);
        [$body, $sig] = explode('.', $q['ticket']);
        $this->assertTrue(hash_equals(HopSignature::b64(hash_hmac('sha256', $body, self::SECRET, true)), $sig));
        $t = json_decode(HopSignature::unb64($body), true);
        $this->assertSame(2, $t['v']);
        $this->assertSame('notehop', $t['aud']);
        $this->assertSame('rani', $t['username']);
        $this->assertSame('hello', $t['bio']);
        $this->assertSame(['admin:notehop'], $t['roles']);
        $this->assertTrue($t['admin']);
        $this->assertSame(1, $t['profile_version']);
        // The same app's admin flag is not granted at another app.
        HopApp::factory()->pixelhop()->create();
        $res = $this->actingAs($u)->withSession(['2fa_verified' => true])
            ->get('/sso/pixelhop?'.http_build_query(['state' => 'abcdefghijklmnop1234', 'return_to' => 'https://p.example.com/auth/helink/callback']));
        parse_str((string) parse_url($res->headers->get('Location'), PHP_URL_QUERY), $q);
        $t = json_decode(HopSignature::unb64(explode('.', $q['ticket'])[0]), true);
        $this->assertFalse($t['admin']);
    }

    public function test_maintenance_and_unknown_apps(): void
    {
        $app = HopApp::factory()->notehop(self::SECRET)->create(['status' => HopApp::STATUS_MAINTENANCE]);
        $u = User::factory()->create();
        $this->actingAs($u)->withSession(['2fa_verified' => true])
            ->get('/sso/notehop?'.http_build_query(['state' => 'abcdefghijklmnop1234', 'return_to' => 'https://note.example.com/auth/sso/callback']))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($u)->withSession(['2fa_verified' => true])->get('/sso/nope?state=abcdefghijklmnop1234&return_to=x')->assertNotFound();
        $app->update(['status' => HopApp::STATUS_OFF]);
        $this->actingAs($u)->withSession(['2fa_verified' => true])->get('/apps/notehop')->assertNotFound();
    }

    // ---- signed HOP API --------------------------------------------------

    private function hopSigned(string $method, string $path, array $body = [], array $over = [], string $secret = self::SECRET)
    {
        $json = $body ? json_encode($body) : '';
        $ts = $over['ts'] ?? (string) time();
        $nonce = $over['nonce'] ?? HopSignature::b64(random_bytes(16));
        $sig = HopSignature::b64(hash_hmac('sha256', HopSignature::stringToSign('notehop', $ts, $nonce, $method, $path, $json), $secret, true));

        return $this->call($method, $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HOP_APP' => $over['app'] ?? 'notehop', 'HTTP_X_HOP_TIMESTAMP' => $ts, 'HTTP_X_HOP_NONCE' => $nonce,
            'HTTP_X_HOP_ACTOR' => 'system', 'HTTP_X_HOP_SIGNATURE' => $sig,
        ], $json);
    }

    public function test_signed_profile_api(): void
    {
        HopApp::factory()->notehop(self::SECRET)->create();
        $u = User::factory()->create(['username' => 'rani']);
        $this->hopSigned('GET', '/api/hop/profile/'.$u->id)->assertOk()
            ->assertJsonPath('data.username', 'rani')->assertJsonPath('data.sub', (string) $u->id)->assertJsonPath('data.admin', false);
        $this->hopSigned('GET', '/api/hop/profiles?ids='.$u->id)->assertOk()->assertJsonPath('data.0.username', 'rani');
        $this->hopSigned('GET', '/api/hop/apps')->assertOk()->assertJsonPath('data.0.slug', 'notehop')->assertJsonMissing(['secret']);
        // Wrong secret, stale timestamp, unknown app, and a replayed nonce are refused.
        $this->hopSigned('GET', '/api/hop/profile/'.$u->id, secret: str_repeat('x', 40))->assertStatus(401);
        $this->hopSigned('GET', '/api/hop/profile/'.$u->id, over: ['ts' => (string) (time() - 900)])->assertStatus(401)->assertJsonPath('error', 'stale_request');
        $this->hopSigned('GET', '/api/hop/profile/'.$u->id, over: ['app' => 'nope'])->assertStatus(404);
        $nonce = HopSignature::b64(random_bytes(16));
        $this->hopSigned('GET', '/api/hop/profile/'.$u->id, over: ['nonce' => $nonce])->assertOk();
        $this->hopSigned('GET', '/api/hop/profile/'.$u->id, over: ['nonce' => $nonce])->assertStatus(401)->assertJsonPath('error', 'replay');
        $this->getJson('/api/hop/profile/'.$u->id)->assertStatus(404);
    }

    public function test_apps_own_forms_use_the_generic_account_api(): void
    {
        HopApp::factory()->notehop(self::SECRET)->create(['capabilities' => ['account_api']]);
        $this->hopSigned('POST', '/api/apps/notehop/register', ['name' => 'Rani', 'email' => 'rani@example.com', 'password' => 'a-Strong-pass-123', 'tos_accepted' => true])
            ->assertCreated()->assertJsonPath('user.username', 'rani')->assertJsonPath('user.verified', false);
        User::where('email', 'rani@example.com')->update(['email_verified_at' => now()]);
        $this->hopSigned('POST', '/api/apps/notehop/login', ['email' => 'rani@example.com', 'password' => 'a-Strong-pass-123'])
            ->assertOk()->assertJsonPath('user.username', 'rani');
        // Without the capability the endpoint does not exist for that app.
        HopApp::where('slug', 'notehop')->update(['capabilities' => json_encode([])]);
        $this->hopSigned('POST', '/api/apps/notehop/login', ['email' => 'rani@example.com', 'password' => 'a-Strong-pass-123'])->assertStatus(404);
    }

    public function test_secret_rotation_accepts_the_previous_secret_for_a_while(): void
    {
        $old = str_repeat('o', 40);
        HopApp::factory()->notehop(self::SECRET)->create(['secret_prev' => $old, 'secret_prev_until' => now()->addDay()]);
        $u = User::factory()->create();
        $this->hopSigned('GET', '/api/hop/profile/'.$u->id, secret: $old)->assertOk();
        HopApp::where('slug', 'notehop')->update(['secret_prev_until' => now()->subMinute()]);
        $this->hopSigned('GET', '/api/hop/profile/'.$u->id, secret: $old)->assertStatus(401);
    }

    // ---- events ----------------------------------------------------------

    public function test_profile_status_and_deletion_events_reach_listening_apps(): void
    {
        Queue::fake();
        HopApp::factory()->notehop(self::SECRET)->create(['capabilities' => ['events']]);
        HopApp::factory()->pixelhop()->create(['capabilities' => []]);
        $u = User::factory()->create(['username' => 'rani']);
        $u->update(['bio' => 'new bio']);
        Queue::assertPushed(SendHopEvent::class, fn ($j) => $j->type === 'profile.updated' && $j->data['bio'] === 'new bio' && $j->data['profile_version'] === 2);
        $u->update(['status' => User::STATUS_SUSPENDED]);
        Queue::assertPushed(SendHopEvent::class, fn ($j) => $j->type === 'account.status' && $j->data['status'] === 'suspended');
        $u->forceFill(['last_login_at' => now()])->saveQuietly();
        $u->delete();
        Queue::assertPushed(SendHopEvent::class, fn ($j) => $j->type === 'account.deleted');
        // Only the app that listens gets jobs.
        Queue::assertPushed(SendHopEvent::class, 3);
    }

    public function test_event_delivery_is_signed_with_the_apps_secret(): void
    {
        $app = HopApp::factory()->notehop(self::SECRET)->create(['capabilities' => ['events']]);
        Http::fake(['https://note.example.com/hop/events' => Http::response(['ok' => true])]);
        (new SendHopEvent($app->id, 'profile.updated', ['sub' => '1'], 'evt-1', '2026-01-01T00:00:00Z'))->handle();
        Http::assertSent(function ($req) {
            $base = HopSignature::stringToSign('helink', $req->header('X-HOP-Timestamp')[0], $req->header('X-HOP-Nonce')[0], 'POST', '/hop/events', $req->body());
            $expected = HopSignature::b64(hash_hmac('sha256', $base, self::SECRET, true));

            return $req->header('X-HOP-Signature')[0] === $expected && json_decode($req->body(), true)['type'] === 'profile.updated';
        });
    }

    // ---- bootstrap -------------------------------------------------------

    public function test_hop_bootstrap_moves_env_settings_into_the_registry(): void
    {
        config(['auth.superadmin_email' => 'boss@example.com', 'services.pixelhop.url' => 'https://p.example.com/',
            'services.pixelhop.sso_secret' => str_repeat('p', 40)]);
        $boss = User::factory()->create(['email' => 'boss@example.com']);
        Artisan::call('hop:bootstrap');
        $this->assertTrue(Role::where('user_id', $boss->id)->where('role', 'owner')->exists());
        $app = HopApp::where('slug', 'pixelhop')->firstOrFail();
        $this->assertSame('https://p.example.com', $app->url);
        $this->assertTrue($app->isConfigured() && $app->can('account_api'));
        Artisan::call('hop:bootstrap'); // idempotent
        $this->assertSame(1, HopApp::count());
        $this->assertSame(1, Role::count());
        Artisan::call('hop:usernames');
        $this->assertNotNull($boss->fresh()->username);
    }
}
