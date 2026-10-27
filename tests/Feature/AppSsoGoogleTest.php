<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class AppSsoGoogleTest extends TestCase
{
    use RefreshDatabase;

    private const STATE = 'abcdefghijklmnop1234';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.notehop.url' => 'https://note.example.com', 'services.notehop.sso_secret' => str_repeat('n', 40),
            'services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret',
        ]);
    }

    private function query(array $over = []): array
    {
        return array_merge(['state' => self::STATE, 'return_to' => 'https://note.example.com/auth/sso/callback'], $over);
    }

    private function ticketUrl(): string
    {
        return route('sso.notehop', $this->query());
    }

    /** Google answers with this person. */
    private function google(string $email, string $id = 'g-123'): void
    {
        $u = (new GoogleUser)->map(['id' => $id, 'email' => $email, 'name' => 'Rani Google', 'avatar' => 'https://lh3.example/a.png']);
        $u->setRaw(['email_verified' => true]);
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($u);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_guest_goes_to_google_with_the_ticket_step_remembered(): void
    {
        $this->get('/sso/notehop/google?'.http_build_query($this->query()))->assertRedirect(route('auth.google', ['from_app' => 1]));
        $this->assertSame($this->ticketUrl(), session('url.intended'));
    }

    public function test_signed_in_user_goes_straight_to_the_ticket(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->withSession(['2fa_verified' => true])
            ->get('/sso/notehop/google?'.http_build_query($this->query()))->assertRedirect($this->ticketUrl());
    }

    public function test_rejects_other_return_addresses(): void
    {
        $this->get('/sso/notehop/google?'.http_build_query($this->query(['return_to' => 'https://evil.example/auth/sso/callback'])))->assertStatus(400);
    }

    public function test_off_without_google_configured(): void
    {
        config(['services.google.client_id' => '']);
        $this->get('/sso/notehop/google?'.http_build_query($this->query()))->assertStatus(404);
    }

    public function test_existing_account_signs_in_and_goes_back_to_the_app(): void
    {
        User::factory()->create(['email' => 'rani@example.com', 'email_verified_at' => now()]);
        $this->google('rani@example.com');
        $this->get('/sso/notehop/google?'.http_build_query($this->query()));
        $this->get(route('auth.google', ['from_app' => 1]));
        $this->get('/auth/google/callback')->assertRedirect($this->ticketUrl());
        $this->assertAuthenticated();
    }

    public function test_new_account_is_made_and_goes_back_to_the_app_not_onboarding(): void
    {
        $this->google('baru@example.com', 'g-new');
        $this->get('/sso/notehop/google?'.http_build_query($this->query()));
        $this->get(route('auth.google', ['from_app' => 1]));
        $this->get('/auth/google/callback')->assertRedirect($this->ticketUrl());
        $u = User::where('email', 'baru@example.com')->first();
        $this->assertNotNull($u);
        $this->assertSame('g-new', $u->google_id);
        $this->assertNotNull($u->email_verified_at);
    }

    public function test_two_factor_is_asked_before_the_app(): void
    {
        $user = User::factory()->create(['email' => 'sari@example.com', 'email_verified_at' => now()]);
        $user->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
        $this->google('sari@example.com', 'g-sari');
        $this->get('/sso/notehop/google?'.http_build_query($this->query()));
        $this->get(route('auth.google', ['from_app' => 1]));
        $this->get('/auth/google/callback')->assertRedirect($this->ticketUrl());
        $this->get($this->ticketUrl())->assertRedirect(route('two-factor.show'));
    }

    public function test_existing_unverified_account_is_verified_by_google(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'lama@example.com']);
        $this->google('lama@example.com', 'g-lama');
        $this->get(route('auth.google'));
        $this->get('/auth/google/callback');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_backfill_verifies_earlier_google_accounts_only(): void
    {
        $google = User::factory()->unverified()->create(['google_id' => 'g-old']);
        $plain = User::factory()->unverified()->create();
        (require database_path('migrations/2026_10_27_000001_verify_google_accounts.php'))->up();
        $this->assertNotNull($google->fresh()->email_verified_at);
        $this->assertNull($plain->fresh()->email_verified_at);
    }

    public function test_plain_google_sign_in_still_refuses_unknown_accounts(): void
    {
        $this->google('nobody@example.com', 'g-x');
        $this->get(route('auth.google'));
        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
