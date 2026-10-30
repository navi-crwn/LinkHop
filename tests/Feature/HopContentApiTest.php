<?php

namespace Tests\Feature;

use App\Models\AbuseFlag;
use App\Models\AbuseReport;
use App\Models\HopApp;
use App\Models\HopAudit;
use App\Models\IpBan;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\SeoSetting;
use App\Models\User;
use App\Support\HopSignature;
use Hop\Bio\Models\BioPage;
use Hop\Safety\Models\DomainBlacklist;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** The content side of the HOP admin API: links, bio pages, the abuse queue, IP, domains, settings. */
#[Group('bio')]
class HopContentApiTest extends TestCase
{
    private const SECRET = 'imperium-secret-that-is-long-enough-1234';

    protected function setUp(): void
    {
        parent::setUp();
        HopApp::factory()->imperium(self::SECRET)->create();
    }

    private function signed(string $method, string $path, array $body = [], string $actor = '7')
    {
        $json = $body ? json_encode($body) : '';
        $ts = (string) time();
        $nonce = HopSignature::b64(random_bytes(16));
        $sig = HopSignature::b64(hash_hmac('sha256', HopSignature::stringToSign('imperium', $ts, $nonce, $method, $path, $json), self::SECRET, true));

        return $this->call($method, $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HOP_APP' => 'imperium', 'HTTP_X_HOP_TIMESTAMP' => $ts, 'HTTP_X_HOP_NONCE' => $nonce,
            'HTTP_X_HOP_ACTOR' => $actor, 'HTTP_X_HOP_SIGNATURE' => $sig,
        ], $json);
    }

    private function act(string $type, int $id, string $action, array $params = [])
    {
        return $this->signed('POST', "/api/hop/content/$type/$id/actions", ['action' => $action, 'reason' => 'test', 'params' => $params]);
    }

    public function test_manifest_and_content_list(): void
    {
        $u = User::factory()->create(['username' => 'rani']);
        Link::create(['slug' => 'live1', 'target_url' => 'https://example.com/a', 'user_id' => $u->id, 'clicks' => 5]);
        Link::create(['slug' => 'off1', 'target_url' => 'https://example.com/b', 'user_id' => $u->id, 'status' => 'inactive']);
        Link::create(['slug' => 'guest1', 'target_url' => 'https://example.com/c']);
        BioPage::create(['user_id' => $u->id, 'slug' => 'rani', 'title' => 'Rani', 'is_published' => true]);
        BioPage::create(['user_id' => $u->id, 'slug' => 'draft', 'title' => 'Draft', 'is_published' => false]);

        $this->signed('GET', '/api/hop/manifest')->assertOk()->assertJsonPath('data.content_types.0.type', 'link')->assertJsonPath('data.extensions.0', 'ip');
        $this->signed('GET', '/api/hop/content?type=link')->assertOk()->assertJsonPath('page.total', 3)->assertJsonPath('data.0.slug', 'guest1')->assertJsonPath('data.0.owner', null);
        $this->signed('GET', '/api/hop/content?type=link&status=live')->assertOk()->assertJsonPath('page.total', 2)->assertJsonPath('data.0.status', 'live')->assertJsonPath('data.1.owner.username', 'rani');
        $this->signed('GET', '/api/hop/content?type=link&status=disabled')->assertOk()->assertJsonPath('data.0.slug', 'off1')->assertJsonPath('data.0.status', 'disabled');
        $this->signed('GET', '/api/hop/content?type=link&owner=guest')->assertOk()->assertJsonPath('page.total', 1);
        $this->signed('GET', '/api/hop/content?type=link&q=example.com/b')->assertOk()->assertJsonPath('page.total', 1);
        $this->signed('GET', '/api/hop/content?type=bio&status=published')->assertOk()->assertJsonPath('page.total', 1)->assertJsonPath('data.0.slug', 'rani');
        $this->signed('GET', '/api/hop/content?type=nope')->assertStatus(422);
        $this->signed('GET', '/api/hop/content?type=link&limit=1')->assertOk()->assertJsonPath('page.next', (string) Link::where('slug', 'guest1')->first()->id);
    }

    public function test_link_actions_and_owner_protection(): void
    {
        $u = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $link = Link::create(['slug' => 'spam', 'target_url' => 'https://bad.example', 'user_id' => $u->id]);
        $ownerLink = Link::create(['slug' => 'boss', 'target_url' => 'https://example.com', 'user_id' => $owner->id]);
        $this->act('link', $link->id, 'disable')->assertOk()->assertJsonPath('data.status', 'disabled');
        $this->assertSame('inactive', $link->fresh()->status);
        AbuseFlag::create(['subject_type' => 'link', 'subject_id' => $link->id, 'user_id' => $u->id, 'score' => 70, 'signals' => [], 'action' => 'disabled', 'status' => 'open', 'summary' => 'x']);
        $this->act('link', $link->id, 'enable')->assertOk()->assertJsonPath('data.status', 'live');
        $this->assertSame('dismissed', AbuseFlag::first()->status, 'enabling dismisses the finding so the scanner leaves it alone');
        $this->act('link', $link->id, 'transfer', ['to' => $owner->id])->assertOk()->assertJsonPath('data.owner.sub', (string) $owner->id);
        $this->act('link', $link->id, 'transfer', ['to' => 999999])->assertStatus(422);
        $this->act('link', $ownerLink->id, 'disable')->assertStatus(403)->assertJsonPath('error', 'owner_protected');
        $this->signed('GET', '/api/hop/content/link/'.$link->id)->assertOk()->assertJsonPath('data.slug', 'spam')->assertJsonPath('data.findings.0.status', 'dismissed');
        $this->act('link', $link->id, 'bogus')->assertStatus(422);
        Link::whereKey($link->id)->update(['user_id' => $u->id]); // the in-memory model still says $u, so update() would be a no-op
        $this->act('link', $link->id, 'delete')->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertDatabaseMissing('links', ['id' => $link->id]);
        $this->signed('GET', '/api/hop/content/link/'.$link->id)->assertStatus(404);
        $this->assertSame(4, HopAudit::where('target_type', 'link')->count()); // disable, enable, transfer, delete; refused actions are not audited
        $this->assertSame('7', HopAudit::first()->actor);
    }

    public function test_bio_actions(): void
    {
        $u = User::factory()->create();
        $p = BioPage::create(['user_id' => $u->id, 'slug' => 'me', 'title' => 'Me', 'is_published' => true]);
        $this->act('bio', $p->id, 'unpublish')->assertOk()->assertJsonPath('data.status', 'draft');
        $this->assertTrue(AbuseFlag::where('subject_type', 'bio_page')->where('subject_id', $p->id)->where('status', 'confirmed')->exists());
        $this->act('bio', $p->id, 'publish')->assertOk()->assertJsonPath('data.status', 'published');
        $this->signed('GET', '/api/hop/content/bio/'.$p->id)->assertOk()->assertJsonPath('data.slug', 'me');
        $this->act('bio', $p->id, 'delete')->assertOk();
        $this->assertDatabaseMissing('bio_pages', ['id' => $p->id]);
    }

    public function test_reports_queue(): void
    {
        $u = User::factory()->create();
        $link = Link::create(['slug' => 'spam', 'target_url' => 'https://bad.example', 'user_id' => $u->id, 'status' => 'inactive']);
        $r = AbuseReport::create(['slug' => 'spam', 'url' => 'https://hel.ink/spam', 'reason' => 'phishing', 'status' => 'open']);
        $f = AbuseFlag::create(['subject_type' => 'link', 'subject_id' => $link->id, 'user_id' => $u->id, 'score' => 80, 'signals' => [], 'action' => 'disabled', 'status' => 'open', 'summary' => 'phishing words']);
        $res = $this->signed('GET', '/api/hop/reports')->assertOk();
        $kinds = collect($res->json('data'))->pluck('kind')->sort()->values()->all();
        $this->assertSame(['flag', 'report'], $kinds);
        $this->signed('POST', "/api/hop/reports/report/{$r->id}/actions", ['action' => 'investigate'])->assertOk()->assertJsonPath('data.status', 'investigating');
        $this->signed('POST', "/api/hop/reports/report/{$r->id}/actions", ['action' => 'close'])->assertOk()->assertJsonPath('data.status', 'closed');
        $this->signed('POST', "/api/hop/reports/flag/{$f->id}/actions", ['action' => 'restore'])->assertOk()->assertJsonPath('data.status', 'dismissed');
        $this->assertSame('active', $link->fresh()->status, 'restoring a finding re-enables the link');
        $this->signed('GET', '/api/hop/reports?status=open')->assertOk()->assertJsonPath('page.total', 0);
        $this->signed('GET', '/api/hop/reports?status=closed')->assertOk()->assertJsonPath('page.total', 2);
    }

    public function test_ip_and_domain_extensions(): void
    {
        $this->signed('POST', '/api/hop/x/ip/actions', ['action' => 'ban', 'params' => ['ip' => '203.0.113.9', 'reason' => 'spam']])->assertOk();
        $this->assertTrue(IpBan::where('ip_address', '203.0.113.9')->exists());
        $this->signed('POST', '/api/hop/x/ip/actions', ['action' => 'ban', 'params' => ['ip' => 'not-an-ip']])->assertStatus(422);
        $this->signed('GET', '/api/hop/x/ip')->assertOk()->assertJsonPath('data.bans.0.ip', '203.0.113.9');
        $this->signed('POST', '/api/hop/x/ip/actions', ['action' => 'unban', 'params' => ['ip' => '203.0.113.9']])->assertOk()->assertJsonPath('data.deleted', 1);
        $this->signed('POST', '/api/hop/x/domains/actions', ['action' => 'add', 'params' => ['domain' => 'https://www.Bad.Example/', 'category' => 'phishing']])->assertOk()->assertJsonPath('data.domain', 'bad.example');
        $this->assertTrue(DomainBlacklist::where('domain', 'bad.example')->where('category', 'phishing')->exists());
        $this->signed('GET', '/api/hop/x/domains?q=bad')->assertOk()->assertJsonPath('page.total', 1);
        $this->signed('POST', '/api/hop/x/domains/actions', ['action' => 'remove', 'params' => ['domain' => 'bad.example']])->assertOk()->assertJsonPath('data.deleted', 1);
        $this->signed('GET', '/api/hop/x/queue')->assertOk()->assertJsonPath('data.worker', 'stale');
        $this->assertSame(4, HopAudit::count());
    }

    public function test_settings_get_and_put(): void
    {
        $res = $this->signed('GET', '/api/hop/settings')->assertOk();
        $keys = collect($res->json('data'))->pluck('key')->all();
        $this->assertContains('seo.site_title', $keys);
        $this->assertContains('abuse.threshold_disable', $keys);
        $this->signed('PUT', '/api/hop/settings', ['seo.site_title' => 'LinkHop test', 'abuse.threshold_disable' => 5])->assertOk();
        $this->assertSame('LinkHop test', SeoSetting::first()->site_title);
        $this->assertSame(60, config('safety.thresholds.disable'), 'read-only keys are ignored');
        $this->signed('PUT', '/api/hop/settings', ['nothing' => 1])->assertStatus(422);
    }

    public function test_needs_admin_client(): void
    {
        HopApp::factory()->notehop()->create();
        $json = '';
        $ts = (string) time();
        $nonce = HopSignature::b64(random_bytes(16));
        $sig = HopSignature::b64(hash_hmac('sha256', HopSignature::stringToSign('notehop', $ts, $nonce, 'GET', '/api/hop/content?type=link', $json), 'notehop-secret-that-is-long-enough-12345', true));
        $this->call('GET', '/api/hop/content?type=link', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_HOP_APP' => 'notehop',
            'HTTP_X_HOP_TIMESTAMP' => $ts, 'HTTP_X_HOP_NONCE' => $nonce, 'HTTP_X_HOP_SIGNATURE' => $sig])->assertStatus(403);
    }

    public function test_global_analytics_extension(): void
    {
        $u = User::factory()->create();
        $l1 = Link::create(['slug' => 'toplink', 'target_url' => 'https://example.com/top', 'user_id' => $u->id, 'clicks' => 15]);
        $l2 = Link::create(['slug' => 'otherlink', 'target_url' => 'https://example.com/other', 'user_id' => $u->id, 'clicks' => 2]);

        LinkClick::create(['link_id' => $l1->id, 'country' => 'ID', 'country_name' => 'Indonesia', 'referer' => 'https://google.com', 'clicked_at' => now()->subDays(2)]);
        LinkClick::create(['link_id' => $l1->id, 'country' => 'ID', 'country_name' => 'Indonesia', 'referer' => 'https://google.com', 'clicked_at' => now()->subDays(1)]);
        LinkClick::create(['link_id' => $l1->id, 'country' => 'US', 'country_name' => 'United States', 'referer' => 'https://twitter.com', 'clicked_at' => now()->subHours(5)]);

        // Manifest should expose analytics extension
        $this->signed('GET', '/api/hop/manifest')->assertOk()->assertJsonPath('data.extensions', ['ip', 'domains', 'queue', 'analytics']);

        $res = $this->signed('GET', '/api/hop/x/analytics');
        $res->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.total_links', 2)
            ->assertJsonPath('data.total_clicks', 17)
            ->assertJsonPath('data.clicks_30d', 3)
            ->assertJsonPath('data.countries.0.country', 'ID')
            ->assertJsonPath('data.countries.0.clicks', 2)
            ->assertJsonPath('data.top_links.0.slug', 'toplink');
    }
}
