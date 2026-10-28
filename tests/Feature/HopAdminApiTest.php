<?php

namespace Tests\Feature;

use App\Http\Controllers\AppSsoController;
use App\Models\ActivityLog;
use App\Models\ApiToken;
use App\Models\HopApp;
use App\Models\HopAudit;
use App\Models\HopUserNote;
use App\Models\Link;
use App\Models\LoginHistory;
use App\Models\Role;
use App\Models\User;
use App\Support\HopSignature;
use Hop\Bio\Models\BioPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** The signed HOP admin API for Imperium: /api/hop/stats, users, actions, audit, search, health. */
#[Group('bio')]
class HopAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'imperium-secret-that-is-long-enough-1234';

    private const NOTEHOP = 'notehop-secret-that-is-long-enough-12345';

    protected function setUp(): void
    {
        parent::setUp();
        HopApp::factory()->imperium(self::SECRET)->create();
        HopApp::factory()->notehop(self::NOTEHOP)->create();
    }

    private function signed(string $method, string $path, array $body = [], string $app = 'imperium', string $secret = self::SECRET, string $actor = '7')
    {
        $json = $body ? json_encode($body) : '';
        $ts = (string) time();
        $nonce = HopSignature::b64(random_bytes(16));
        $sig = HopSignature::b64(hash_hmac('sha256', HopSignature::stringToSign($app, $ts, $nonce, $method, $path, $json), $secret, true));

        return $this->call($method, $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HOP_APP' => $app, 'HTTP_X_HOP_TIMESTAMP' => $ts, 'HTTP_X_HOP_NONCE' => $nonce,
            'HTTP_X_HOP_ACTOR' => $actor, 'HTTP_X_HOP_SIGNATURE' => $sig,
        ], $json);
    }

    private function asNotehop(string $method, string $path, array $body = [])
    {
        return $this->signed($method, $path, $body, 'notehop', self::NOTEHOP);
    }

    private function act(User $user, string $action, array $params = [], ?string $reason = 'because')
    {
        return $this->signed('POST', '/api/hop/users/'.$user->id.'/actions', ['action' => $action, 'reason' => $reason, 'params' => $params]);
    }

    // ---- gating -------------------------------------------------------------

    public function test_admin_routes_need_the_admin_client_capability(): void
    {
        $u = User::factory()->create();
        foreach (['/api/hop/stats', '/api/hop/users', '/api/hop/users/'.$u->id, '/api/hop/audit', '/api/hop/search?q=a'] as $path) {
            $this->asNotehop('GET', $path)->assertStatus(403)->assertJson(['ok' => false, 'error' => 'forbidden']);
        }
        $this->asNotehop('POST', '/api/hop/users/'.$u->id.'/actions', ['action' => 'suspend'])->assertStatus(403);
        $this->assertSame(User::STATUS_ACTIVE, $u->fresh()->status);
        // The shared routes still work for every app; health needs only a signature.
        $this->asNotehop('GET', '/api/hop/profile/'.$u->id)->assertOk();
        $this->asNotehop('GET', '/api/hop/health')->assertOk()->assertJsonPath('data.app', 'linkhop');
        $this->getJson('/api/hop/stats')->assertStatus(404);
        $this->signed('GET', '/api/hop/stats', secret: str_repeat('x', 40))->assertStatus(401);
    }

    public function test_apps_registry_carries_secrets_only_for_an_admin_client(): void
    {
        $this->asNotehop('GET', '/api/hop/apps')->assertOk()->assertJsonMissing(['secret' => self::NOTEHOP]);
        $res = $this->signed('GET', '/api/hop/apps')->assertOk();
        $apps = collect($res->json('data'))->keyBy('slug');
        $this->assertSame(self::NOTEHOP, $apps['notehop']['secret']);
        $this->assertSame(self::SECRET, $apps['imperium']['secret']);
        $this->assertTrue($apps['imperium']['hidden']);
        $this->assertFalse($apps['notehop']['hidden']);
    }

    // ---- stats --------------------------------------------------------------

    public function test_stats_has_kpis_and_daily_series(): void
    {
        $u = User::factory()->create();
        User::factory()->create(['status' => User::STATUS_SUSPENDED]);
        $link = Link::create(['slug' => 'abc', 'target_url' => 'https://example.com', 'user_id' => $u->id]);
        Link::create(['slug' => 'off', 'target_url' => 'https://example.com', 'user_id' => $u->id, 'status' => 'disabled']);
        DB::table('link_clicks')->insert([
            ['link_id' => $link->id, 'clicked_at' => now()],
            ['link_id' => $link->id, 'clicked_at' => now()->subDays(2)],
            ['link_id' => $link->id, 'clicked_at' => now()->subDays(40)],
        ]);
        BioPage::create(['user_id' => $u->id, 'slug' => 'me', 'title' => 'Me']);
        $res = $this->signed('GET', '/api/hop/stats')->assertOk()->assertJsonPath('ok', true);
        $kpi = $res->json('data.kpi');
        $this->assertSame(['users', 'users_new_7d', 'users_suspended', 'links', 'links_live', 'bio_pages', 'clicks_30d', 'clicks_today', 'open_reports', 'open_flags'], array_keys($kpi));
        $this->assertSame(2, $kpi['users']);
        $this->assertSame(1, $kpi['users_suspended']);
        $this->assertSame(2, $kpi['links']);
        $this->assertSame(1, $kpi['links_live']);
        $this->assertSame(1, $kpi['bio_pages']);
        $this->assertSame(2, $kpi['clicks_30d']);
        $this->assertSame(1, $kpi['clicks_today']);
        $this->assertSame(0, $kpi['open_reports']);
        $series = $res->json('data.series');
        $this->assertCount(14, $series['signups']);
        $this->assertCount(14, $series['clicks']);
        $this->assertSame(now()->toDateString(), $series['signups'][13][0]);
        $this->assertSame(2, $series['signups'][13][1]);
        $this->assertSame(1, $series['clicks'][13][1]);
        $this->assertSame(1, $series['clicks'][11][1]);
        // Cached for a minute, so a new user does not show up yet.
        User::factory()->create();
        $this->assertSame(2, $this->signed('GET', '/api/hop/stats')->json('data.kpi.users'));
        Cache::flush();
        $this->assertSame(3, $this->signed('GET', '/api/hop/stats')->json('data.kpi.users'));
        $this->assertCount(3, $this->signed('GET', '/api/hop/stats?since='.now()->subDays(2)->toDateString().'&until='.now()->toDateString())->json('data.series.clicks'));
        $this->signed('GET', '/api/hop/stats?since=2030-01-01&until=2020-01-01')->assertStatus(422)->assertJsonPath('error', 'bad_range');
    }

    // ---- users --------------------------------------------------------------

    public function test_users_list_filters_and_paginates(): void
    {
        $rani = User::factory()->admin('pixelhop')->create(['username' => 'rani', 'name' => 'Rani Putri', 'email' => 'rani@example.com']);
        $bob = User::factory()->create(['username' => 'bob', 'name' => 'Bob', 'email' => 'bob@example.com', 'status' => User::STATUS_SUSPENDED]);
        $third = User::factory()->create(['username' => 'cat']);
        Link::create(['slug' => 'r1', 'target_url' => 'https://example.com', 'user_id' => $rani->id]);
        $res = $this->signed('GET', '/api/hop/users')->assertOk()->assertJsonPath('page.total', 3)->assertJsonPath('page.next', null);
        $this->assertSame([(string) $third->id, (string) $bob->id, (string) $rani->id], array_column($res->json('data'), 'sub'));
        $row = $res->json('data.2');
        $this->assertSame(['sub', 'username', 'name', 'email', 'status', 'roles', 'verified', 'created_at', 'last_login_at', 'links', 'bio_pages'], array_keys($row));
        $this->assertSame(['admin:pixelhop'], $row['roles']);
        $this->assertSame(1, $row['links']);
        $this->assertTrue($row['verified']);
        // q: username, name, email or exact id.
        $this->assertSame(['rani'], array_column($this->signed('GET', '/api/hop/users?q=Putri')->json('data'), 'username'));
        $this->assertSame(['bob'], array_column($this->signed('GET', '/api/hop/users?q=bob%40')->json('data'), 'username'));
        $this->assertSame(['cat'], array_column($this->signed('GET', '/api/hop/users?q='.$third->id)->json('data'), 'username'));
        $this->assertSame(['bob'], array_column($this->signed('GET', '/api/hop/users?status=suspended')->json('data'), 'username'));
        $this->assertSame(['rani'], array_column($this->signed('GET', '/api/hop/users?role=admin:pixelhop')->json('data'), 'username'));
        $this->assertSame([], $this->signed('GET', '/api/hop/users?role=owner')->json('data'));
        // Cursor: pages of 2, newest first.
        $p1 = $this->signed('GET', '/api/hop/users?limit=2')->assertJsonPath('page.next', (string) $bob->id)->assertJsonPath('page.total', 3);
        $this->assertCount(2, $p1->json('data'));
        $p2 = $this->signed('GET', '/api/hop/users?limit=2&cursor='.$bob->id)->assertJsonPath('page.next', null);
        $this->assertSame(['rani'], array_column($p2->json('data'), 'username'));
    }

    public function test_user_360(): void
    {
        $u = User::factory()->admin()->create(['username' => 'rani']);
        $link = Link::create(['slug' => 'r1', 'target_url' => 'https://example.com', 'user_id' => $u->id, 'clicks' => 3]);
        Link::create(['slug' => 'r2', 'target_url' => 'https://example.com/2', 'user_id' => $u->id, 'status' => 'disabled']);
        DB::table('link_clicks')->insert([['link_id' => $link->id, 'clicked_at' => now()], ['link_id' => $link->id, 'clicked_at' => now()->subDays(45)]]);
        BioPage::create(['user_id' => $u->id, 'slug' => 'rani', 'title' => 'Rani']);
        ApiToken::create(['user_id' => $u->id, 'name' => 't', 'token' => hash('sha256', 'x')]);
        DB::table('sessions')->insert([
            ['id' => 's1', 'user_id' => $u->id, 'payload' => '', 'last_activity' => 1700000000],
            ['id' => 's2', 'user_id' => $u->id, 'payload' => '', 'last_activity' => 1700000500],
        ]);
        LoginHistory::create(['user_id' => $u->id, 'ip_address' => '1.2.3.4', 'country' => 'ID', 'city' => 'Jakarta', 'device' => 'Desktop', 'browser' => 'Firefox', 'provider' => 'password', 'is_proxy' => true, 'logged_in_at' => now()]);
        ActivityLog::create(['user_id' => $u->id, 'action' => 'created', 'model' => 'Link', 'model_id' => $link->id, 'description' => 'made r1']);
        HopUserNote::create(['user_id' => $u->id, 'author' => '7', 'text' => 'watch this one']);
        $res = $this->signed('GET', '/api/hop/users/'.$u->id)->assertOk();
        $d = $res->json('data');
        $this->assertSame(['profile', 'counts', 'sessions', 'logins', 'activity', 'roles', 'notes', 'recent_links'], array_keys($d));
        $this->assertSame('rani', $d['profile']['username']);
        $this->assertTrue($d['profile']['admin']);
        $this->assertFalse($d['profile']['owner']);
        $this->assertSame(['links' => 2, 'links_live' => 1, 'bio_pages' => 1, 'folders' => 0, 'tags' => 0, 'api_tokens' => 1, 'clicks_30d' => 1], $d['counts']);
        $this->assertSame(2, $d['sessions']['count']);
        $this->assertSame('2023-11-14T22:21:40+00:00', $d['sessions']['last_activity']);
        $this->assertSame('Jakarta', $d['logins'][0]['city']);
        $this->assertTrue($d['logins'][0]['is_proxy']);
        $this->assertSame('made r1', $d['activity'][0]['description']);
        $this->assertSame(['admin'], $d['roles']);
        $this->assertSame('watch this one', $d['notes'][0]['text']);
        $this->assertSame('r2', $d['recent_links'][0]['slug']);
        $this->assertSame(3, $d['recent_links'][1]['clicks']);
        $this->signed('GET', '/api/hop/users/999999')->assertStatus(404)->assertJsonPath('error', 'not_found');
    }

    // ---- actions ------------------------------------------------------------

    public function test_suspend_and_activate_are_audited(): void
    {
        $u = User::factory()->create();
        $this->act($u, 'suspend', reason: 'spam')->assertOk()->assertJsonPath('data.status', 'suspended')->assertJsonPath('data.sub', (string) $u->id);
        $this->assertSame(User::STATUS_SUSPENDED, $u->fresh()->status);
        $this->act($u, 'activate')->assertOk()->assertJsonPath('data.status', 'active');
        $rows = HopAudit::orderBy('id')->get();
        $this->assertSame(['suspend', 'activate'], $rows->pluck('action')->all());
        $this->assertSame('7', $rows[0]->actor);
        $this->assertSame('imperium', $rows[0]->app_slug);
        $this->assertSame('user', $rows[0]->target_type);
        $this->assertSame((string) $u->id, $rows[0]->target_id);
        $this->assertSame('spam', $rows[0]->reason);
        $this->act($u, 'nope')->assertStatus(422)->assertJsonPath('error', 'unknown_action');
    }

    public function test_owner_cannot_be_suspended_or_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $this->act($owner, 'suspend')->assertStatus(403)->assertJsonPath('error', 'owner_protected');
        $this->act($owner, 'delete')->assertStatus(403)->assertJsonPath('error', 'owner_protected');
        $this->assertSame(User::STATUS_ACTIVE, $owner->fresh()->status);
        $this->assertSame(0, HopAudit::count());
        // The legacy SUPERADMIN_EMAIL owner is protected too.
        config(['auth.superadmin_email' => 'boss@example.com']);
        $legacy = User::factory()->create(['email' => 'boss@example.com']);
        $this->act($legacy, 'suspend')->assertStatus(403);
    }

    public function test_revoke_sessions_and_tokens(): void
    {
        $u = User::factory()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 's1', 'user_id' => $u->id, 'payload' => '', 'last_activity' => 1],
            ['id' => 's2', 'user_id' => $other->id, 'payload' => '', 'last_activity' => 1],
        ]);
        ApiToken::create(['user_id' => $u->id, 'name' => 'a', 'token' => hash('sha256', 'a')]);
        ApiToken::create(['user_id' => $other->id, 'name' => 'b', 'token' => hash('sha256', 'b')]);
        $this->act($u, 'revoke_sessions')->assertOk();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $u->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->act($u, 'revoke_tokens')->assertOk();
        $this->assertSame(0, $u->apiTokens()->count());
        $this->assertSame(1, $other->apiTokens()->count());
        $this->assertSame(['deleted' => 1], HopAudit::where('action', 'revoke_sessions')->first()->params);
        $this->assertSame(['deleted' => 1], HopAudit::where('action', 'revoke_tokens')->first()->params);
    }

    public function test_set_roles_replaces_roles_and_keeps_the_last_owner(): void
    {
        $u = User::factory()->admin('pixelhop')->create();
        $res = $this->act($u, 'set_roles', ['roles' => ['moderator', 'admin:notehop']])->assertOk();
        $this->assertEqualsCanonicalizing(['moderator', 'admin:notehop'], $res->json('data.roles'));
        $this->assertEqualsCanonicalizing(['moderator', 'admin:notehop'], $u->fresh()->roleNames());
        $this->assertNull(Role::where('user_id', $u->id)->first()->granted_by); // actor 7 is not a user here
        $this->act($u, 'set_roles', ['roles' => ['king']])->assertStatus(422)->assertJsonPath('error', 'bad_params');
        $this->act($u, 'set_roles', ['roles' => 'admin'])->assertStatus(422);
        $this->act($u, 'set_roles', ['roles' => ['moderator:notehop']])->assertStatus(422);
        // Making someone owner, then stripping the only owner, is refused.
        $owner = User::factory()->owner()->create();
        $this->act($owner, 'set_roles', ['roles' => ['admin']])->assertStatus(409)->assertJsonPath('error', 'last_owner');
        $this->assertTrue($owner->fresh()->isOwner());
        $this->act($u, 'set_roles', ['roles' => ['owner']])->assertOk();
        $this->act($owner, 'set_roles', ['roles' => []])->assertOk()->assertJsonPath('data.roles', []);
        $this->assertFalse($owner->fresh()->isOwner());
        $this->assertTrue($u->fresh()->isOwner());
        $this->assertSame(['roles' => ['owner']], HopAudit::where('target_id', (string) $u->id)->latest('id')->first()->params);
    }

    public function test_note_action(): void
    {
        $u = User::factory()->create();
        $this->act($u, 'note', ['text' => 'Asked for a refund twice'])->assertOk();
        $this->act($u, 'note', ['text' => '  '])->assertStatus(422)->assertJsonPath('error', 'bad_params');
        $note = HopUserNote::where('user_id', $u->id)->sole();
        $this->assertSame('Asked for a refund twice', $note->text);
        $this->assertSame('7', $note->author);
        $this->assertSame(['note_id' => $note->id], HopAudit::where('action', 'note')->sole()->params);
        $this->assertSame('Asked for a refund twice', $this->signed('GET', '/api/hop/users/'.$u->id)->json('data.notes.0.text'));
    }

    public function test_delete_action_removes_the_account_and_its_data(): void
    {
        $u = User::factory()->create(['email' => 'gone@example.com']);
        $link = Link::create(['slug' => 'gone', 'target_url' => 'https://example.com', 'user_id' => $u->id]);
        BioPage::create(['user_id' => $u->id, 'slug' => 'gone', 'title' => 'Gone']);
        $this->act($u, 'delete', reason: 'ToS')->assertOk()->assertJsonPath('data.deleted', true)->assertJsonPath('data.email', 'gone@example.com');
        $this->assertNull(User::find($u->id));
        $this->assertNull(Link::find($link->id));
        $this->assertSame(0, BioPage::where('user_id', $u->id)->count());
        $audit = HopAudit::where('action', 'delete')->sole();
        $this->assertSame((string) $u->id, $audit->target_id);
        $this->assertSame('ToS', $audit->reason);
        $this->assertSame('gone@example.com', $audit->params['email']);
    }

    // ---- audit, search, health -------------------------------------------------

    public function test_audit_lists_newest_first_with_a_cursor(): void
    {
        $u = User::factory()->create();
        $this->act($u, 'suspend');
        $this->act($u, 'activate');
        $this->act($u, 'note', ['text' => 'x']);
        $res = $this->signed('GET', '/api/hop/audit?limit=2')->assertOk()->assertJsonPath('page.total', 3);
        $this->assertSame(['note', 'activate'], array_column($res->json('data'), 'action'));
        $this->assertSame(['id', 'actor', 'app_slug', 'action', 'target_type', 'target_id', 'reason', 'params', 'created_at'], array_keys($res->json('data.0')));
        $next = $res->json('page.next');
        $this->assertNotNull($next);
        $res = $this->signed('GET', '/api/hop/audit?limit=2&cursor='.$next)->assertJsonPath('page.next', null);
        $this->assertSame(['suspend'], array_column($res->json('data'), 'action'));
        $this->assertCount(3, $this->signed('GET', '/api/hop/audit?target='.$u->id)->json('data'));
    }

    public function test_search_spans_users_links_and_bio_pages(): void
    {
        $u = User::factory()->create(['username' => 'rani', 'name' => 'Rani Putri', 'email' => 'rani@example.com']);
        $link = Link::create(['slug' => 'rani-site', 'target_url' => 'https://example.com', 'user_id' => $u->id]);
        Link::create(['slug' => 'xyz', 'target_url' => 'https://rani.example.org', 'user_id' => null]);
        $bio = BioPage::create(['user_id' => $u->id, 'slug' => 'ranipage', 'title' => 'Hi']);
        $res = $this->signed('GET', '/api/hop/search?q=rani')->assertOk();
        $rows = $res->json('data');
        $this->assertSame(['user', 'link', 'link', 'bio'], array_column($rows, 'kind'));
        $this->assertSame((string) $u->id, $rows[0]['id']);
        $this->assertSame((string) $u->id, $rows[0]['sub']);
        $this->assertNull($rows[0]['url']); // users are opened in the control center itself
        $this->assertSame('xyz', Link::find($rows[1]['id'])->slug);
        $this->assertNull($rows[1]['sub']);
        $this->assertSame(public_url('l/xyz'), $rows[1]['url']);
        $this->assertSame(route('bio.public.show', 'ranipage'), $rows[3]['url']);
        $this->assertSame((string) $link->id, $rows[2]['id']);
        $this->assertSame((string) $bio->id, $rows[3]['id']);
        $this->assertSame([], $this->signed('GET', '/api/hop/search?q=')->json('data'));
        $this->assertSame([], $this->signed('GET', '/api/hop/search?q=nothing-here')->json('data'));
    }

    public function test_health(): void
    {
        $res = $this->signed('GET', '/api/hop/health')->assertOk();
        $d = $res->json('data');
        $this->assertSame('linkhop', $d['app']);
        $this->assertSame('ok', $d['db']);
        $this->assertSame('stale', $d['queue']);
        $this->assertMatchesRegularExpression('/^([0-9a-f]{7}|dev)$/', $d['version']);
        $this->assertNotEmpty($d['time']);
        Cache::put('queue:heartbeat', now()->toDateTimeString(), 300);
        $this->assertSame('ok', $this->signed('GET', '/api/hop/health')->json('data.queue'));
        Cache::put('queue:heartbeat', now()->subMinutes(11)->toDateTimeString(), 300);
        $this->assertSame('stale', $this->signed('GET', '/api/hop/health')->json('data.queue'));
    }

    // ---- bootstrap, menus ------------------------------------------------------

    public function test_bootstrap_registers_imperium_as_a_hidden_admin_client(): void
    {
        HopApp::query()->delete();
        config(['services.imperium.url' => 'https://imperium.example.com/', 'services.imperium.sso_secret' => str_repeat('i', 40)]);
        Artisan::call('hop:bootstrap');
        $app = HopApp::where('slug', 'imperium')->firstOrFail();
        $this->assertSame('https://imperium.example.com', $app->url);
        $this->assertTrue($app->getAttribute('hidden'));
        $this->assertTrue($app->can('admin_client') && $app->isConfigured());
        $this->assertSame(['default' => '/'], $app->pages);
        $this->assertSame(1, $app->sort_order);
        $this->assertNull($app->menu_label);
        Artisan::call('hop:bootstrap');
        $this->assertSame(1, HopApp::count());
        // Hidden: not in the menus, still found for SSO and allowed as a CSP form-action origin.
        $this->assertSame([], HopApp::active()->pluck('slug')->all());
        $this->assertNotNull(HopApp::find_('imperium'));
        $this->assertSame([], array_map(fn ($a) => $a->slug, AppSsoController::apps()));
        $this->assertContains('https://imperium.example.com', AppSsoController::origins());
        $u = User::factory()->create();
        $this->actingAs($u)->withSession(['2fa_verified' => true])->get('/dashboard')->assertOk()->assertDontSee('Imperium');
        $this->actingAs($u)->withSession(['2fa_verified' => true])
            ->get('/sso/imperium?'.http_build_query(['state' => 'abcdefghijklmnop1234', 'return_to' => 'https://imperium.example.com/auth/sso/callback']))
            ->assertRedirect();
    }
}
