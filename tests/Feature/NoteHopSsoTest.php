<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class NoteHopSsoTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'notehop-secret-that-is-long-enough-12345';

    private const PIXELHOP_SECRET = 'pixelhop-secret-that-is-long-enough-1234';

    private const STATE = 'abcdefghijklmnop1234';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.notehop.url' => 'https://note.example.com', 'services.notehop.sso_secret' => self::SECRET,
            'services.pixelhop.url' => 'https://p.example.com', 'services.pixelhop.sso_secret' => self::PIXELHOP_SECRET,
        ]);
    }

    private function authorizeUrl(array $over = []): string
    {
        return '/sso/notehop?'.http_build_query(array_merge([
            'state' => self::STATE,
            'return_to' => 'https://note.example.com/auth/sso/callback',
        ], $over));
    }

    private function sign(string $body, string $secret): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $body, $secret, true)), '+/', '-_'), '=');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get($this->authorizeUrl())->assertRedirect('/login');
    }

    public function test_ticket_is_for_notehop_and_signed_with_its_own_secret(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $res = $this->actingAs($user)->withSession(['2fa_verified' => true])->get($this->authorizeUrl());
        $location = (string) $res->headers->get('Location');
        $this->assertStringStartsWith('https://note.example.com/auth/sso/callback?ticket=', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $q);
        [$body, $sig] = explode('.', $q['ticket']);
        $this->assertTrue(hash_equals($this->sign($body, self::SECRET), $sig), 'signed with the NoteHop secret');
        $this->assertFalse(hash_equals($this->sign($body, self::PIXELHOP_SECRET), $sig), 'not valid for PixelHop');
        $t = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
        $this->assertSame('notehop', $t['aud']);
        $this->assertSame((string) $user->id, $t['sub']);
        $this->assertSame(self::STATE, $t['state']);
        $this->assertLessThanOrEqual(time() + 120, $t['exp']);
    }

    public function test_rejects_other_return_addresses(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        foreach (['https://evil.example/auth/sso/callback', 'https://p.example.com/auth/helink/callback', 'https://note.example.com/auth/helink/callback'] as $to) {
            $this->actingAs($user)->withSession(['2fa_verified' => true])->get($this->authorizeUrl(['return_to' => $to]))->assertStatus(400);
        }
    }

    public function test_two_factor_is_asked_first_then_continues_to_notehop(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->get($this->authorizeUrl())->assertRedirect(route('two-factor.show'));
        // After the code, Laravel's intended URL brings them back to the ticket step.
        $this->assertStringContainsString('/sso/notehop', (string) session('url.intended'));
    }

    public function test_disabled_without_configuration(): void
    {
        config(['services.notehop.sso_secret' => '']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->withSession(['2fa_verified' => true])->get($this->authorizeUrl())->assertStatus(404);
    }

    public function test_notes_menu_opens_notehop_signed_in(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->withSession(['2fa_verified' => true])->get('/notehop')
            ->assertRedirect('https://note.example.com/auth/sso?next=%2Fdashboard');
        $this->actingAs($user)->withSession(['2fa_verified' => true])->get('/notehop?to=new')
            ->assertRedirect('https://note.example.com/auth/sso?next=%2F');
    }

    public function test_new_account_from_notehop_goes_back_after_verifying(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(),
            ['id' => $user->id, 'hash' => sha1($user->email)]);
        $res = $this->actingAs($user)->withSession(['2fa_verified' => true, 'url.intended' => 'https://hel.example.com'.$this->authorizeUrl()])->get($url);
        $res->assertRedirect(route('notes.open', ['to' => 'default']));
        $this->get(route('notes.open', ['to' => 'default']))->assertRedirect('https://note.example.com/auth/sso?next=%2Fdashboard');
    }

    public function test_onboarding_offers_the_way_back_to_notehop(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->withSession(['2fa_verified' => true, 'url.intended' => 'https://hel.example.com'.$this->authorizeUrl()])
            ->get('/onboarding')->assertSee('Continue to NoteHop')->assertDontSee('Continue to PixelHop');
    }
}
