<?php

namespace Tests\Feature;

use App\Models\HopApp;
use App\Models\Link;
use App\Models\User;
use App\Support\GuestDigest;
use App\Support\HopSignature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** links:guest-digest and GET /api/hop/x/guest-digest: new guest links by destination domain, spikes marked. */
class GuestDigestTest extends TestCase
{
    private const SECRET = 'imperium-secret-that-is-long-enough-1234';

    protected function setUp(): void
    {
        parent::setUp();
        HopApp::factory()->imperium(self::SECRET)->create();
        File::deleteDirectory(storage_path('app/sweeps'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/sweeps'));
        parent::tearDown();
    }

    private function signed(string $method, string $path)
    {
        $ts = (string) time();
        $nonce = HopSignature::b64(random_bytes(16));
        $sig = HopSignature::b64(hash_hmac('sha256', HopSignature::stringToSign('imperium', $ts, $nonce, $method, $path, ''), self::SECRET, true));

        return $this->call($method, $path, [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'HTTP_X_HOP_APP' => 'imperium', 'HTTP_X_HOP_TIMESTAMP' => $ts,
            'HTTP_X_HOP_NONCE' => $nonce, 'HTTP_X_HOP_ACTOR' => '7', 'HTTP_X_HOP_SIGNATURE' => $sig,
        ]);
    }

    public function test_digest_counts_guest_links_by_domain_marks_spikes_and_is_served_to_imperium(): void
    {
        config(['shortener.guest_policy.digest_spike' => 3]);
        $user = User::factory()->create();
        foreach (range(1, 3) as $i) {
            Link::create(['slug' => "kit{$i}", 'target_url' => "https://sub{$i}.kit-domain.test/p{$i}", 'status' => 'active', 'safety_status' => 'pending', 'creator_hash' => str_repeat('a', 40)]);
        }
        Link::create(['slug' => 'ok1', 'target_url' => 'https://github.com/x', 'status' => 'active', 'creator_hash' => str_repeat('b', 40)]);
        Link::create(['slug' => 'dis1', 'target_url' => 'https://www.kit-domain.test/q', 'status' => 'inactive', 'safety_status' => 'flagged']);
        Link::create(['slug' => 'old1', 'target_url' => 'https://kit-domain.test/old', 'status' => 'active'])->forceFill(['created_at' => now()->subDays(2)])->save();
        Link::create(['slug' => 'acct1', 'target_url' => 'https://kit-domain.test/acct', 'status' => 'active', 'user_id' => $user->id]);

        $this->artisan('links:guest-digest')
            ->expectsOutputToContain('Guest links in the last 24h: 5 (3 pending, 0 cleared, 1 disabled, 0 flags, 2 creators).')
            ->expectsOutputToContain('Spikes (>= 3 new guest links on one domain): kit-domain.test')
            ->expectsOutputToContain('Imperium push: off (HOP_NOTIFY_URL not set).')
            ->assertSuccessful();

        $digest = Cache::get(GuestDigest::CACHE_KEY);
        $this->assertSame(5, $digest['guest_links']);
        $this->assertSame('kit-domain.test', $digest['domains'][0]['domain']);
        $this->assertSame(4, $digest['domains'][0]['links']);
        $this->assertTrue($digest['domains'][0]['spike']);
        $this->assertSame('github.com', $digest['domains'][1]['domain']);
        $this->assertFalse($digest['domains'][1]['spike']);
        $this->assertCount(1, File::files(storage_path('app/sweeps')));

        $this->signed('GET', '/api/hop/x/guest-digest')->assertOk()->assertJsonPath('data.spikes.0', 'kit-domain.test')->assertJsonPath('data.guest_links', 5);
        $this->signed('GET', '/api/hop/manifest')->assertOk()->assertJsonPath('data.extensions.4', 'guest_digest');
        $this->signed('GET', '/api/hop/stats')->assertOk()->assertJsonPath('data.kpi.guest_links_24h', 5)->assertJsonPath('data.kpi.guest_links_pending', 3);
        $this->getJson('/api/hop/x/guest-digest')->assertNotFound();
    }

    public function test_digest_is_built_on_the_fly_when_none_was_stored(): void
    {
        Link::create(['slug' => 'g1', 'target_url' => 'https://example.org/a', 'status' => 'active']);

        $this->signed('GET', '/api/hop/x/guest-digest')->assertOk()->assertJsonPath('data.guest_links', 1)->assertJsonPath('data.domains.0.domain', 'example.org');
    }

    public function test_digest_is_pushed_to_imperium_as_a_signed_request_when_configured(): void
    {
        config(['hop.notify_url' => 'http://127.0.0.1:8100', 'hop-auth.secret' => str_repeat('k', 40), 'hop-auth.app' => 'linkhop']);
        Http::fake(['127.0.0.1:8100/hop/notify' => Http::response(['ok' => true], 202)]);
        Link::create(['slug' => 'g1', 'target_url' => 'https://example.org/a', 'status' => 'active']);

        $this->artisan('links:guest-digest')->expectsOutputToContain('Imperium push: HTTP 202.')->assertSuccessful();

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            $expected = HopSignature::b64(hash_hmac('sha256', HopSignature::stringToSign('linkhop', $request->header('X-HOP-Timestamp')[0], $request->header('X-HOP-Nonce')[0], 'POST', '/hop/notify', $request->body()), str_repeat('k', 40), true));

            return $request->header('X-HOP-App')[0] === 'linkhop'
                && $request->header('X-HOP-Signature')[0] === $expected
                && $body['type'] === 'guest.digest' && $body['app'] === 'linkhop' && $body['data']['guest_links'] === 1
                && ! str_contains($request->body(), '127.0.0.1');
        });
        $this->artisan('links:guest-digest', ['--no-push' => true])->assertSuccessful();
        Http::assertSentCount(1);
    }
}
