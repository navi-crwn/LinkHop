<?php

use App\Models\Link;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\UserDeletion;
use App\Support\WebhookGuard;
use App\Support\Webhooks;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

const HOOK_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

/** Makes every host name resolve to the given addresses, so the tests need no network. */
function hookDns(array $ips = ['93.184.216.34']): void
{
    app()->bind(WebhookGuard::class, fn () => new class($ips) extends WebhookGuard
    {
        public function __construct(private array $ips) {}

        protected function resolveHost(string $host): array
        {
            return filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) ? [trim($host, '[]')] : $this->ips;
        }
    });
}

/** The webhook events sent so far (other requests, such as the click's location lookup, are not webhooks). */
function hookEvents(): array
{
    return collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn ($r) => $r->hasHeader('X-LinkHop-Event'))
        ->map(fn ($r) => $r->header('X-LinkHop-Event')[0])->values()->all();
}

function hookFor(User $user, array $attrs = []): WebhookEndpoint
{
    return $user->webhookEndpoints()->create($attrs + ['url' => 'https://hooks.example.net/in', 'secret' => 'whsec_test']);
}

function hookLink(User $user, array $attrs = []): Link
{
    static $n = 0;
    $n++;

    return Link::create($attrs + ['user_id' => $user->id, 'slug' => 'hook'.$n, 'target_url' => 'https://example.com/h'.$n, 'status' => 'active']);
}

beforeEach(fn () => hookDns());

test('an endpoint is added with a secret that is shown once and stored encrypted', function () {
    $user = User::factory()->create();
    $res = $this->actingAs($user)->post('/settings/webhooks', ['url' => 'https://hooks.example.net/in?token=abc', 'events' => ['link.created', 'link.deleted']]);
    $res->assertRedirect(route('settings'))->assertSessionHas('webhook_secret');
    $secret = session('webhook_secret')['secret'];
    $endpoint = $user->webhookEndpoints()->first();
    expect($secret)->toStartWith('whsec_')->and($endpoint->secret)->toBe($secret)->and($endpoint->events)->toBe(['link.created', 'link.deleted']);
    expect(DB::table('webhook_endpoints')->value('secret'))->not->toContain($secret);
    // the settings page shows the host only (a path may hold a token) and never the secret again
    $this->actingAs($user)->get('/settings'); // the page that follows the redirect shows the secret
    $page = $this->actingAs($user)->get('/settings')->assertOk()->assertSee('hooks.example.net');
    $page->assertDontSee('token=abc')->assertDontSee($secret);
});

test('the secret appears on the page right after it is made, once', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/settings/webhooks', ['url' => 'https://hooks.example.net/in']);
    $secret = session('webhook_secret')['secret'];
    $this->actingAs($user)->get('/settings')->assertSee($secret);
    $this->actingAs($user)->get('/settings')->assertDontSee($secret);
});

test('only https addresses on public networks are accepted', function (string $url) {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/settings/webhooks', ['url' => $url])->assertSessionHasErrors('url');
    expect($user->webhookEndpoints()->count())->toBe(0);
})->with([
    'plain http' => 'http://hooks.example.net/in',
    'loopback' => 'https://127.0.0.1/in',
    'loopback v6' => 'https://[::1]/in',
    'private 10/8' => 'https://10.1.2.3/in',
    'private 192.168' => 'https://192.168.1.10/in',
    'link-local metadata' => 'https://169.254.169.254/latest/meta-data',
    'shared address space' => 'https://100.64.0.5/in',
    'ipv4 mapped loopback' => 'https://[::ffff:127.0.0.1]/in',
    'localhost' => 'https://localhost/in',
    'internal suffix' => 'https://db.internal/in',
    'login in the address' => 'https://user:pass@hooks.example.net/in',
    'not an address' => 'hooks.example.net',
    'other scheme' => 'ftp://hooks.example.net/in',
]);

test('a host that resolves to a private address is refused', function () {
    hookDns(['93.184.216.34', '10.0.0.7']);
    $user = User::factory()->create();
    $this->actingAs($user)->post('/settings/webhooks', ['url' => 'https://sneaky.example.net/in'])->assertSessionHasErrors('url');
});

test('an account can have five webhooks, no more', function () {
    $user = User::factory()->create();
    foreach (range(1, 5) as $i) {
        hookFor($user, ['url' => "https://hooks.example.net/{$i}"]);
    }
    $this->actingAs($user)->post('/settings/webhooks', ['url' => 'https://hooks.example.net/6'])->assertSessionHasErrors('url');
    expect($user->webhookEndpoints()->count())->toBe(5);
});

test('a new link is sent signed, and the receiver can check the signature', function () {
    Http::fake(['hooks.example.net/*' => Http::response('ok', 200)]);
    $user = User::factory()->create();
    $endpoint = hookFor($user);
    $link = hookLink($user);
    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($link) {
        $body = $request->body();
        $expected = 'sha256='.hash_hmac('sha256', $request->header('X-LinkHop-Timestamp')[0].'.'.$body, 'whsec_test');
        $json = json_decode($body, true);

        return $request->url() === 'https://hooks.example.net/in'
            && $request->header('X-LinkHop-Signature')[0] === $expected
            && $request->header('X-LinkHop-Event')[0] === 'link.created'
            && $request->header('X-LinkHop-Delivery')[0] === $json['id']
            && abs((int) $request->header('X-LinkHop-Timestamp')[0] - time()) < 5
            && $json['event'] === 'link.created'
            && $json['data']['link']['slug'] === $link->slug
            && ! str_contains($body, 'password_hash');
    });
    $delivery = $endpoint->deliveries()->first();
    expect($delivery->status)->toBe('sent')->and($delivery->http_code)->toBe(200)->and($delivery->attempts)->toBe(1);
});

test('only the events an endpoint asked for are sent', function () {
    Http::fake();
    $user = User::factory()->create();
    hookFor($user, ['events' => ['link.deleted']]);
    $link = hookLink($user);
    Http::assertNothingSent();
    $link->delete();
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => $r->header('X-LinkHop-Event')[0] === 'link.deleted');
});

test('someone else’s links, guest links and switched-off endpoints send nothing', function () {
    Http::fake();
    $user = User::factory()->create();
    hookFor($user);
    hookFor($user, ['url' => 'https://off.example.net/in', 'is_active' => false]);
    hookLink(User::factory()->create());
    Link::create(['user_id' => null, 'slug' => 'guest1', 'target_url' => 'https://example.com/g', 'status' => 'active']);
    Http::assertNothingSent();
});

test('link.updated is sent for real changes only, not for clicks or QR files', function () {
    Http::fake();
    $user = User::factory()->create();
    hookFor($user, ['events' => ['link.updated']]);
    $link = hookLink($user);
    $link->update(['clicks' => 5, 'last_clicked_at' => now(), 'qr_code_path' => 'qrcodes/x.png']);
    Http::assertNothingSent();
    $link->update(['target_url' => 'https://example.com/new', 'password_hash' => bcrypt('secret1')]);
    Http::assertSentCount(1);
    Http::assertSent(function (Request $r) {
        $json = json_decode($r->body(), true);

        return $json['event'] === 'link.updated' && $json['data']['changed'] === ['target_url', 'password']
            && $json['data']['link']['password_protected'] === true && ! str_contains($r->body(), '$2y$');
    });
});

test('link.limit_reached is sent once, when the last allowed click is taken', function () {
    Http::fake();
    $user = User::factory()->create();
    hookFor($user, ['events' => ['link.limit_reached']]);
    $link = hookLink($user, ['max_clicks' => 2]);
    $this->withHeader('User-Agent', HOOK_UA)->get('/l/'.$link->slug)->assertRedirect($link->target_url);
    expect(hookEvents())->toBe([]);
    $this->withHeader('User-Agent', HOOK_UA)->get('/l/'.$link->slug)->assertRedirect($link->target_url);
    expect(hookEvents())->toBe(['link.limit_reached']);
    Http::assertSent(function (Request $r) use ($link) {
        $json = json_decode($r->body(), true);

        return ($json['event'] ?? null) === 'link.limit_reached' && $json['data']['link']['slug'] === $link->slug && $json['data']['link']['clicks'] === 2;
    });
    $this->withHeader('User-Agent', HOOK_UA)->get('/l/'.$link->slug)->assertStatus(410);
    expect(hookEvents())->toBe(['link.limit_reached']);
});

test('link.expired is sent once for a link whose time ran out', function () {
    Http::fake();
    $user = User::factory()->create();
    hookFor($user, ['events' => ['link.expired']]);
    hookLink($user, ['expires_at' => now()->addHour()]);
    $old = hookLink($user, ['expires_at' => now()->subWeek()]);
    $gone = hookLink($user, ['expires_at' => now()->subMinutes(10)]);
    expect(Webhooks::sweepExpired())->toBe(1);
    expect(Webhooks::sweepExpired())->toBe(0);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => json_decode($r->body(), true)['data']['link']['slug'] === $gone->slug);
});

test('a failed delivery is retried after 1, 5, 30 and 120 minutes, then given up', function () {
    Http::fake(['hooks.example.net/*' => Http::response('nope', 500)]);
    $user = User::factory()->create();
    $endpoint = hookFor($user);
    hookLink($user);
    $d = $endpoint->deliveries()->first();
    expect($d->status)->toBe('pending')->and($d->attempts)->toBe(1)->and($d->error)->toBe('http')->and($d->http_code)->toBe(500);
    expect($d->next_attempt_at->diffInMinutes(now(), true))->toBeGreaterThan(0.9)->toBeLessThan(1.1);
    expect(Webhooks::retryDue())->toBe(0); // not yet
    foreach ([[1, 2], [5, 3], [30, 4], [120, 5]] as [$wait, $attempts]) {
        $this->travel($wait)->minutes();
        expect(Webhooks::retryDue())->toBe(1);
        expect($d->fresh()->attempts)->toBe($attempts);
    }
    $d = $d->fresh();
    expect($d->status)->toBe('failed')->and($d->next_attempt_at)->toBeNull();
    $this->travel(1)->days();
    expect(Webhooks::retryDue())->toBe(0);
    Http::assertSentCount(5);
    // each try carries the same delivery id and a fresh timestamp
    expect(collect(Http::recorded())->map(fn ($pair) => $pair[0]->header('X-LinkHop-Delivery')[0])->unique()->count())->toBe(1);
    expect($endpoint->fresh()->failure_streak)->toBe(1);
});

test('a retry that works ends the delivery and clears the failure count', function () {
    Http::fake(['hooks.example.net/*' => Http::sequence()->push('', 503)->push('ok', 204)]);
    $user = User::factory()->create();
    $endpoint = hookFor($user, ['failure_streak' => 3]);
    hookLink($user);
    $this->travel(2)->minutes();
    Webhooks::retryDue();
    $d = $endpoint->deliveries()->first();
    expect($d->status)->toBe('sent')->and($d->attempts)->toBe(2)->and($d->http_code)->toBe(204)->and($endpoint->fresh()->failure_streak)->toBe(0);
});

test('a redirect answer is a failure: redirects are not followed', function () {
    Http::fake(['hooks.example.net/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]);
    $user = User::factory()->create();
    $endpoint = hookFor($user);
    hookLink($user);
    expect($endpoint->deliveries()->first()->status)->toBe('pending');
    Http::assertSentCount(1);
});

test('an address that became private is refused at delivery time and never called', function () {
    Http::fake();
    $user = User::factory()->create();
    $endpoint = hookFor($user);
    DB::table('webhook_endpoints')->update(['url' => 'https://10.0.0.9/in']);
    hookLink($user);
    $d = $endpoint->deliveries()->first();
    expect($d->status)->toBe('failed')->and($d->error)->toBe('blocked')->and($d->attempts)->toBe(1);
    Http::assertNothingSent();
});

test('a host whose DNS answer turned private after it was added is refused', function () {
    Http::fake();
    $user = User::factory()->create();
    $endpoint = hookFor($user);
    hookDns(['127.0.0.1']);
    hookLink($user);
    expect($endpoint->deliveries()->first()->error)->toBe('blocked');
    Http::assertNothingSent();
});

test('ten deliveries in a row that fail for good switch the endpoint off', function () {
    Http::fake(['hooks.example.net/*' => Http::response('', 500)]);
    $user = User::factory()->create();
    $endpoint = hookFor($user, ['failure_streak' => 9]);
    hookLink($user);
    foreach ([1, 5, 30, 120] as $wait) {
        $this->travel($wait)->minutes();
        Webhooks::retryDue();
    }
    expect($endpoint->fresh()->is_active)->toBeFalse()->and($endpoint->fresh()->failure_streak)->toBe(10);
});

test('the test button sends a ping, even to a switched-off endpoint, and a failed ping does not count', function () {
    Http::fake(['hooks.example.net/*' => Http::response('', 500)]);
    $user = User::factory()->create();
    $endpoint = hookFor($user, ['is_active' => false]);
    $this->actingAs($user)->post("/settings/webhooks/{$endpoint->id}/test")->assertRedirect(route('settings'));
    Http::assertSent(fn (Request $r) => $r->header('X-LinkHop-Event')[0] === 'ping');
    expect($endpoint->deliveries()->first()->event)->toBe('ping')->and($endpoint->fresh()->failure_streak)->toBe(0);
});

test('only the owner can test, switch, renew or delete an endpoint', function () {
    Http::fake();
    $owner = User::factory()->create();
    $endpoint = hookFor($owner);
    $other = User::factory()->create();
    $this->actingAs($other)->post("/settings/webhooks/{$endpoint->id}/test")->assertNotFound();
    $this->actingAs($other)->patch("/settings/webhooks/{$endpoint->id}", ['is_active' => 0])->assertNotFound();
    $this->actingAs($other)->post("/settings/webhooks/{$endpoint->id}/rotate")->assertNotFound();
    $this->actingAs($other)->delete("/settings/webhooks/{$endpoint->id}")->assertNotFound();
    expect($endpoint->fresh()->is_active)->toBeTrue()->and($endpoint->fresh()->secret)->toBe('whsec_test');
    $this->actingAs($owner)->patch("/settings/webhooks/{$endpoint->id}", ['is_active' => 0]);
    expect($endpoint->fresh()->is_active)->toBeFalse();
    $this->actingAs($owner)->delete("/settings/webhooks/{$endpoint->id}");
    expect(WebhookEndpoint::count())->toBe(0);
});

test('a new secret replaces the old one and signs from then on', function () {
    Http::fake();
    $user = User::factory()->create();
    $endpoint = hookFor($user);
    $this->actingAs($user)->post("/settings/webhooks/{$endpoint->id}/rotate")->assertSessionHas('webhook_secret');
    $new = session('webhook_secret')['secret'];
    expect($endpoint->fresh()->secret)->toBe($new)->not->toBe('whsec_test');
    hookLink($user);
    Http::assertSent(function (Request $r) use ($new) {
        return $r->header('X-LinkHop-Signature')[0] === 'sha256='.hash_hmac('sha256', $r->header('X-LinkHop-Timestamp')[0].'.'.$r->body(), $new);
    });
});

test('only the last 50 deliveries of an endpoint are kept', function () {
    Http::fake();
    $user = User::factory()->create();
    $endpoint = hookFor($user);
    foreach (range(1, 55) as $i) {
        Webhooks::queue($endpoint, 'ping', []);
    }
    expect($endpoint->deliveries()->count())->toBe(50);
});

test('deleting the account removes its endpoints and sends no "deleted" events on the way', function () {
    Http::fake();
    $user = User::factory()->create();
    hookFor($user);
    hookLink($user);
    Http::assertSentCount(1); // link.created
    app(UserDeletion::class)->purge($user);
    expect(WebhookEndpoint::count())->toBe(0)->and(WebhookDelivery::count())->toBe(0);
    Http::assertSentCount(1);
});

test('a webhook problem never breaks saving the link', function () {
    $user = User::factory()->create();
    hookFor($user);
    Http::fake(fn () => throw new RuntimeException('network exploded'));
    expect(hookLink($user)->exists)->toBeTrue();
});
