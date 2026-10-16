<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PixelHopSsoTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret-that-is-long-enough-1234567890';

    private const STATE = 'abcdefghijklmnop1234';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pixelhop.url' => 'https://p.example.com', 'services.pixelhop.sso_secret' => self::SECRET]);
    }

    private function authorizeUrl(array $over = []): string
    {
        return '/sso/pixelhop?'.http_build_query(array_merge([
            'state' => self::STATE,
            'return_to' => 'https://p.example.com/auth/helink/callback',
        ], $over));
    }

    private function ticketFrom(string $location): array
    {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $q);
        [$body, $sig] = explode('.', $q['ticket']);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $body, self::SECRET, true)), '+/', '-_'), '=');
        $this->assertTrue(hash_equals($expected, $sig), 'signature');

        return json_decode(base64_decode(strtr($body, '-_', '+/')), true);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get($this->authorizeUrl())->assertRedirect('/login');
    }

    public function test_signed_ticket_for_verified_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $res = $this->actingAs($user)->withSession(['2fa_verified' => true])->get($this->authorizeUrl());
        $res->assertRedirect();
        $location = $res->headers->get('Location');
        $this->assertStringStartsWith('https://p.example.com/auth/helink/callback?ticket=', $location);
        $t = $this->ticketFrom($location);
        $this->assertSame((string) $user->id, $t['sub']);
        $this->assertSame($user->email, $t['email']);
        $this->assertSame(self::STATE, $t['state']);
        $this->assertLessThanOrEqual(time() + 120, $t['exp']);
    }

    public function test_rejects_foreign_return_address_and_bad_state(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->withSession(['2fa_verified' => true])
            ->get($this->authorizeUrl(['return_to' => 'https://evil.example/auth/helink/callback']))->assertStatus(400);
        $this->actingAs($user)->withSession(['2fa_verified' => true])
            ->get($this->authorizeUrl(['state' => 'x']))->assertStatus(400);
    }

    public function test_unverified_password_user_must_verify_first(): void
    {
        $user = User::factory()->create(['email_verified_at' => null, 'google_id' => null]);
        $this->actingAs($user)->withSession(['2fa_verified' => true])
            ->get($this->authorizeUrl())->assertRedirect(route('verification.notice'));
    }

    public function test_disabled_without_configuration(): void
    {
        config(['services.pixelhop.sso_secret' => '']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->withSession(['2fa_verified' => true])->get($this->authorizeUrl())->assertStatus(404);
    }

    public function test_images_menu_opens_pixelhop(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->withSession(['2fa_verified' => true])->get('/pixelhop')
            ->assertRedirect('https://p.example.com/auth/helink?next=%2Fdashboard');
    }

    public function test_new_account_from_pixelhop_goes_back_after_verifying(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(),
            ['id' => $user->id, 'hash' => sha1($user->email)]);
        $res = $this->actingAs($user)->withSession(['2fa_verified' => true, 'url.intended' => 'https://hel.example.com'.$this->authorizeUrl()])->get($url);
        $res->assertRedirect(route('images.open', ['to' => 'upload']));
        $this->get(route('images.open', ['to' => 'upload']))->assertRedirect('https://p.example.com/auth/helink?next=%2F');
    }

    public function test_onboarding_offers_the_way_back_to_pixelhop(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->withSession(['2fa_verified' => true, 'url.intended' => 'https://hel.example.com'.$this->authorizeUrl()])
            ->get('/onboarding')->assertSee('Continue to PixelHop');
        $this->flushSession();
        $this->actingAs($user)->withSession(['2fa_verified' => true])->get('/onboarding')->assertDontSee('Continue to PixelHop');
    }
}
