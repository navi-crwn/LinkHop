<?php

use App\Models\AbuseReport;
use App\Models\Link;
use App\Models\User;
use App\Support\GuestClaim;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Claim token for guest links (D1)
|--------------------------------------------------------------------------
| The browser that made a guest link holds a random token in a cookie; the link stores only a keyed hash of it.
| Signed in, that browser can move its links to the account, once.
*/

function claimGuestLink(string $url = 'https://github.com/navi-crwn/LinkHop', ?string $token = null)
{
    $test = test();
    if ($token !== null) {
        $test = $test->withCookie(GuestClaim::COOKIE, $token);
    }

    return $test->post('/shorten', ['target_url' => $url, 'cf-turnstile-response' => 'x']);
}

/** The token in the cookie a response set (the cookie is encrypted like every other cookie of the app). */
function claimTokenOf($response): string
{
    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === GuestClaim::COOKIE);
    expect($cookie)->not->toBeNull();

    return CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false));
}

function claimCookieOf($response)
{
    return collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === GuestClaim::COOKIE);
}

function claimAs(User $user, ?string $token)
{
    $test = test()->actingAs($user);
    if ($token !== null) {
        $test = $test->withCookie(GuestClaim::COOKIE, $token);
    }

    return $test->post('/links/claim');
}

test('the migration adds the hash column and takes it away again', function () {
    $migration = require base_path('database/migrations/2026_11_13_000002_add_claim_token_to_links_table.php');
    expect(Schema::hasColumn('links', 'claim_token_hash'))->toBeTrue()->and(Schema::hasIndex('links', ['claim_token_hash']))->toBeTrue();
    $migration->down();
    expect(Schema::hasColumn('links', 'claim_token_hash'))->toBeFalse();
    $migration->up();
    $migration->up();
    expect(Schema::hasColumn('links', 'claim_token_hash'))->toBeTrue();
});

test('a guest link sets the claim cookie: HttpOnly, Secure, SameSite=Lax, 30 days', function () {
    $res = claimGuestLink();
    $cookie = claimCookieOf($res);
    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getPath())->toBe('/')
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->timestamp)->toBeLessThanOrEqual(now()->addDays(30)->addMinute()->timestamp);
});

test('only a hash of the token is stored, and the token is nowhere else', function () {
    $res = claimGuestLink();
    $token = claimTokenOf($res);
    $link = Link::sole();
    expect($token)->toMatch('/^[a-f0-9]{48}$/')
        ->and($link->claim_token_hash)->toBe(GuestClaim::hash($token))->not->toBe($token)
        ->and(DB::table('links')->get()->toJson())->not->toContain($token)
        ->and($res->headers->get('Location'))->not->toContain($token)
        ->and($res->getContent())->not->toContain($token)
        ->and(json_encode($link->toArray()))->not->toContain('claim_token_hash');
    // the landing page the guest is sent to shows the new link and no token
    $page = $this->withCookie(GuestClaim::COOKIE, $token)->get('/')->assertOk();
    expect($page->getContent())->not->toContain($token);
});

test('a second guest link from the same browser reuses the token', function () {
    $token = claimTokenOf(claimGuestLink());
    claimGuestLink('https://github.com/navi-crwn/BioHop', $token);
    expect(Link::pluck('claim_token_hash')->unique()->all())->toBe([GuestClaim::hash($token)]);
});

test('claim: the signed-in browser sees the notice and moves its links to the account, once', function () {
    $token = claimTokenOf(claimGuestLink());
    claimGuestLink('https://github.com/navi-crwn/BioHop', $token);
    $user = User::factory()->create();

    $this->actingAs($user)->withCookie(GuestClaim::COOKIE, $token)->get('/dashboard')
        ->assertOk()->assertSee('You made 2 links before signing in.')->assertSee('Claim');

    $res = claimAs($user, $token)->assertRedirect(route('dashboard'));
    expect(Link::where('user_id', $user->id)->count())->toBe(2)
        ->and(Link::whereNull('user_id')->count())->toBe(0)
        ->and(Link::whereNotNull('claim_token_hash')->count())->toBe(0);
    $cleared = claimCookieOf($res);
    expect($cleared)->not->toBeNull()->and($cleared->getExpiresTime())->toBeLessThan(time());
    $this->followRedirects($res)->assertSee('2 links are now in your account.');

    // the claimed link no longer expires as a guest link and answers from the account
    $slug = $user->links()->first()->slug;
    $this->get('/l/'.$slug)->assertRedirect();
});

test('claiming twice moves nothing the second time', function () {
    $token = claimTokenOf(claimGuestLink());
    $user = User::factory()->create();
    claimAs($user, $token);
    claimAs($user, $token)->assertRedirect(route('dashboard'));
    expect(Link::where('user_id', $user->id)->count())->toBe(1);
    $other = User::factory()->create();
    claimAs($other, $token);
    expect(Link::where('user_id', $other->id)->count())->toBe(0);
});

test('without the cookie nothing is claimed, even with a stolen link id or slug', function () {
    $token = claimTokenOf(claimGuestLink());
    $link = Link::sole();
    $thief = User::factory()->create();
    claimAs($thief, null)->assertRedirect(route('dashboard'));
    $this->actingAs($thief)->post('/links/claim', ['id' => $link->id, 'slug' => $link->slug, 'link_id' => $link->id, 'token' => $link->claim_token_hash])->assertRedirect();
    claimAs($thief, str_repeat('a', 48));
    claimAs($thief, GuestClaim::hash($token)); // the stored hash is not a token
    expect($link->fresh()->user_id)->toBeNull()->and(Link::where('user_id', $thief->id)->count())->toBe(0);
    $this->actingAs($thief)->get('/dashboard')->assertOk()->assertDontSee('before signing in');
});

test('a link that is owned, switched off, flagged, reported or expired is not claimed', function () {
    $token = claimTokenOf(claimGuestLink());
    $hash = GuestClaim::hash($token);
    $mk = fn (array $a) => Link::create($a + ['slug' => 'c'.substr(md5(json_encode($a)), 0, 8), 'target_url' => 'https://example.com/x', 'status' => 'active', 'claim_token_hash' => $hash]);
    $owner = User::factory()->create();
    $owned = $mk(['user_id' => $owner->id]);
    $off = $mk(['status' => 'inactive']);
    $flagged = $mk(['safety_status' => Link::SAFETY_FLAGGED]);
    $reported = $mk(['target_url' => 'https://example.com/reported']);
    AbuseReport::create(['slug' => $reported->slug, 'url' => 'https://example.com/reported', 'reason' => 'phishing', 'ip_address' => '203.0.113.9', 'status' => 'open']);
    $expired = $mk(['target_url' => 'https://example.com/old']);
    DB::table('links')->where('id', $expired->id)->update(['created_at' => now()->subDays(60)]);

    $user = User::factory()->create();
    claimAs($user, $token);
    expect(Link::where('user_id', $user->id)->count())->toBe(1) // only the link the guest really made
        ->and($owned->fresh()->user_id)->toBe($owner->id)
        ->and($off->fresh()->user_id)->toBeNull()
        ->and($flagged->fresh()->user_id)->toBeNull()
        ->and($reported->fresh()->user_id)->toBeNull()
        ->and($expired->fresh()->user_id)->toBeNull();
});

test('a browser without the cookie costs the dashboard no claim query', function () {
    $user = User::factory()->create();
    DB::enableQueryLog();
    $this->actingAs($user)->get('/dashboard')->assertOk();
    $claimQueries = array_filter(array_column(DB::getQueryLog(), 'query'), fn ($q) => str_contains($q, 'claim_token_hash'));
    expect($claimQueries)->toBe([]);
});

test('the claim endpoint is for signed-in people and is rate limited', function () {
    $this->post('/links/claim')->assertRedirectContains('/auth/sso');
    $user = User::factory()->create();
    foreach (range(1, 6) as $_) {
        claimAs($user, null)->assertRedirect();
    }
    claimAs($user, null)->assertStatus(429);
});

test('the per-address guest limits are untouched: the creator hash is still stored', function () {
    claimGuestLink();
    expect(Link::sole()->creator_hash)->not->toBeNull();
    $token = claimTokenOf(claimGuestLink('https://github.com/navi-crwn/PixelHop'));
    claimAs(User::factory()->create(), $token);
    expect(Link::whereNotNull('creator_hash')->count())->toBe(2);
});
