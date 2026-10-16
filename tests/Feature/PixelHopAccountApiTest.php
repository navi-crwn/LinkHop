<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PixelHopAccountApiTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret-that-is-long-enough-1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pixelhop.url' => 'https://p.example.com', 'services.pixelhop.sso_secret' => self::SECRET]);
        Mail::fake();
        Notification::fake();
    }

    private function signed(string $path, array $body, ?int $ts = null, string $secret = self::SECRET)
    {
        $json = json_encode($body);
        $ts ??= time();
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', 'pixelhop-api|'.$ts.'|'.$json, $secret, true)), '+/', '-_'), '=');

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PIXELHOP_TIMESTAMP' => (string) $ts, 'HTTP_X_PIXELHOP_SIGNATURE' => $sig,
        ], $json);
    }

    public function test_login_with_right_password(): void
    {
        $u = User::factory()->create(['email' => 'cs@example.com', 'password' => Hash::make('random123'), 'email_verified_at' => now()]);
        $this->signed('/api/pixelhop/login', ['email' => 'CS@example.com', 'password' => 'random123'])
            ->assertOk()->assertJsonPath('user.sub', (string) $u->id)->assertJsonPath('user.verified', true);
        $this->signed('/api/pixelhop/login', ['email' => 'cs@example.com', 'password' => 'nope'])->assertStatus(401);
    }

    public function test_requires_a_fresh_valid_signature(): void
    {
        User::factory()->create(['email' => 'cs@example.com', 'password' => Hash::make('random123')]);
        $body = ['email' => 'cs@example.com', 'password' => 'random123'];
        $this->signed('/api/pixelhop/login', $body, secret: 'another-secret-that-is-long-enough-12345678')->assertStatus(401);
        $this->signed('/api/pixelhop/login', $body, ts: time() - 600)->assertStatus(401);
        $this->postJson('/api/pixelhop/login', $body)->assertStatus(401);
    }

    public function test_two_factor_and_suspended_accounts_are_not_signed_in(): void
    {
        User::factory()->create(['email' => 'tf@example.com', 'password' => Hash::make('random123'),
            'two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()]);
        $this->signed('/api/pixelhop/login', ['email' => 'tf@example.com', 'password' => 'random123'])->assertStatus(409);
        $s = User::factory()->create(['email' => 'sus@example.com', 'password' => Hash::make('random123')]);
        $s->forceFill(['status' => User::STATUS_SUSPENDED])->save();
        $this->signed('/api/pixelhop/login', ['email' => 'sus@example.com', 'password' => 'random123'])->assertStatus(403);
    }

    public function test_register_makes_an_unverified_account(): void
    {
        $this->signed('/api/pixelhop/register', ['name' => 'New', 'email' => 'new@example.com', 'password' => 'a-Strong-pass-123', 'tos_accepted' => true])
            ->assertCreated()->assertJsonPath('user.verified', false);
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        $this->signed('/api/pixelhop/register', ['name' => 'New', 'email' => 'new@example.com', 'password' => 'a-Strong-pass-123', 'tos_accepted' => true])
            ->assertStatus(422);
    }

    public function test_attempts_per_email_are_limited(): void
    {
        User::factory()->create(['email' => 'cs@example.com', 'password' => Hash::make('random123')]);
        for ($i = 0; $i < 10; $i++) {
            $this->signed('/api/pixelhop/login', ['email' => 'cs@example.com', 'password' => 'wrong'.$i]);
        }
        $this->signed('/api/pixelhop/login', ['email' => 'cs@example.com', 'password' => 'random123'])->assertStatus(429);
    }
}
