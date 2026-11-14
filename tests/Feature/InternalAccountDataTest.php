<?php

use App\Http\Middleware\VerifyHubRequest;
use App\Models\Comment;
use App\Models\Folder;
use App\Models\Link;
use App\Models\QrCode;
use App\Models\Tag;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\HopSignature;
use Hop\Bio\Models\BioPage;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
| The hub's door into this app: export what an account holds here, and purge
| it when the account is deleted. Signed with the shared SSO secret.
*/
const HUB_SECRET = 'a-shared-sso-secret-that-is-long-enough-1234';

function hubSigned(string $method, string $path, array $body = [], ?int $ts = null, string $secret = HUB_SECRET)
{
    $ts ??= time();
    $json = $body ? json_encode($body) : '';
    $sig = VerifyHubRequest::sign($secret, $ts, $method, $path, $json);
    $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_X_HOP_TIMESTAMP' => (string) $ts, 'HTTP_X_HOP_SIGNATURE' => $sig];

    return test()->call($method, $path, [], [], [], $server, $json);
}

beforeEach(fn () => config(['hop-auth.secret' => HUB_SECRET]));

test('the export lists the account’s links, folders and tags', function () {
    $user = User::factory()->create();
    $folder = Folder::create(['user_id' => $user->id, 'name' => 'Work']);
    Link::create(['user_id' => $user->id, 'folder_id' => $folder->id, 'slug' => 'exp001', 'target_url' => 'https://example.com/e', 'status' => 'active']);

    hubSigned('GET', "/internal/accounts/{$user->id}/export")->assertOk()
        ->assertJsonPath('app', 'linkhop')
        ->assertJsonPath('data.links.0.slug', 'exp001')
        ->assertJsonPath('data.links.0.folder', 'Work')
        ->assertJsonPath('data.folders.0.name', 'Work');
});

test('the purge removes links, QR codes, folders and tags but not the user row', function () {
    Storage::fake('public');
    Storage::disk('public')->put('qr/x.png', 'x');
    $user = User::factory()->create();
    Folder::create(['user_id' => $user->id, 'name' => 'Work']);
    Link::create(['user_id' => $user->id, 'slug' => 'prg001', 'target_url' => 'https://example.com/p', 'status' => 'active', 'qr_code_path' => 'qr/x.png']);

    hubSigned('DELETE', "/internal/accounts/{$user->id}")->assertOk()->assertJsonPath('data.links', 1)->assertJsonPath('data.folders', 1);

    expect(Link::where('slug', 'prg001')->exists())->toBeFalse()
        ->and(Folder::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(User::find($user->id))->not->toBeNull();
    Storage::disk('public')->assertMissing('qr/x.png');
});

test('an unsigned, mis-signed or stale request is refused', function () {
    $user = User::factory()->create();

    $this->getJson("/internal/accounts/{$user->id}/export")->assertUnauthorized();
    hubSigned('GET', "/internal/accounts/{$user->id}/export", secret: 'another-secret-that-is-also-long-enough-xx')->assertUnauthorized();
    hubSigned('GET', "/internal/accounts/{$user->id}/export", ts: time() - 600)->assertUnauthorized();
    hubSigned('DELETE', "/internal/accounts/{$user->id}", secret: 'another-secret-that-is-also-long-enough-xx')->assertUnauthorized();
    expect(User::find($user->id))->not->toBeNull();
});

test('the purge deletes qr files passed in body even after the user row was cascaded', function () {
    $this->withMiddleware(ValidateCsrfToken::class);
    Storage::fake('public');
    Storage::disk('public')->put('qrcodes/link-99.svg', '<svg/>');
    Storage::disk('public')->put('qrcodes/keep.svg', '<svg/>');

    // Simulate hub having already cascaded the users/links rows or calling with explicit qr_codes
    hubSigned('DELETE', '/internal/accounts/999999', [
        'qr_codes' => ['qrcodes/link-99.svg', '../outside.txt'],
    ])->assertOk();

    Storage::disk('public')->assertMissing('qrcodes/link-99.svg');
    Storage::disk('public')->assertExists('qrcodes/keep.svg');
});

test('hop events account.deleted deletes qr files with both hex and hop1 signatures', function () {
    $this->withMiddleware(ValidateCsrfToken::class);
    Storage::fake('public');
    Storage::disk('public')->put('qrcodes/event-qr.png', 'png');
    Storage::disk('public')->put('qrcodes/hop1-qr.png', 'png');

    hubSigned('POST', '/hop/events', [
        'id' => 'evt-1',
        'type' => 'account.deleted',
        'data' => [
            'sub' => '888888',
            'qr_codes' => ['qrcodes/event-qr.png'],
        ],
    ])->assertOk();
    Storage::disk('public')->assertMissing('qrcodes/event-qr.png');

    $ts = (string) time();
    $nonce = HopSignature::b64(random_bytes(16));
    $json = json_encode([
        'id' => 'evt-2',
        'type' => 'account.deleted',
        'data' => [
            'sub' => '888888',
            'qr_codes' => ['qrcodes/hop1-qr.png'],
        ],
    ]);
    $sig = HopSignature::b64(hash_hmac('sha256', HopSignature::stringToSign('helink', $ts, $nonce, 'POST', '/hop/events', $json), HUB_SECRET, true));
    $this->call('POST', '/hop/events', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HOP_APP' => 'helink',
        'HTTP_X_HOP_TIMESTAMP' => $ts,
        'HTTP_X_HOP_NONCE' => $nonce,
        'HTTP_X_HOP_SIGNATURE' => $sig,
    ], $json)->assertOk();
    Storage::disk('public')->assertMissing('qrcodes/hop1-qr.png');
});

test('requests signed with previous secret during rotation transition are accepted', function () {
    $currentSecret = 'linkhop-new-secret-that-is-long-enough-1234';
    $previousSecret = 'linkhop-old-secret-that-is-long-enough-5678';

    config([
        'hop-auth.secret' => $currentSecret,
        'hop-auth.secret_prev' => $previousSecret,
    ]);

    $user = User::factory()->create();

    // Request signed with current secret is accepted
    hubSigned('GET', "/internal/accounts/{$user->id}/export", secret: $currentSecret)->assertOk();

    // Request signed with previous secret during transition is accepted
    hubSigned('GET', "/internal/accounts/{$user->id}/export", secret: $previousSecret)->assertOk();

    // HOP-1 signed request with previous secret is also accepted
    $ts = (string) time();
    $nonce = HopSignature::b64(random_bytes(16));
    $path = "/internal/accounts/{$user->id}/export";
    $sig = HopSignature::b64(hash_hmac('sha256', HopSignature::stringToSign('helink', $ts, $nonce, 'GET', $path, ''), $previousSecret, true));
    test()->call('GET', $path, [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HOP_APP' => 'helink',
        'HTTP_X_HOP_TIMESTAMP' => $ts,
        'HTTP_X_HOP_NONCE' => $nonce,
        'HTTP_X_HOP_SIGNATURE' => $sig,
    ])->assertOk();

    // Request signed with an unknown secret is refused
    hubSigned('GET', "/internal/accounts/{$user->id}/export", secret: 'unknown-secret-that-is-long-enough-9999')->assertUnauthorized();
});

test('the summary gives counts only, for this account', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = Link::create(['user_id' => $user->id, 'slug' => 'sum001', 'target_url' => 'https://example.com/s', 'status' => 'active']);
    Link::create(['user_id' => $other->id, 'slug' => 'sum002', 'target_url' => 'https://example.com/o', 'status' => 'active']);
    $mine->clicks()->create(['clicked_at' => now()->subDay()]);
    $mine->clicks()->create(['clicked_at' => now()->subDays(20)]);

    hubSigned('GET', "/internal/accounts/{$user->id}/summary")->assertOk()
        ->assertJsonPath('app', 'linkhop')
        ->assertJsonPath('data.links', 1)
        ->assertJsonPath('data.clicks_7d', 1)
        ->assertJsonMissingPath('data.links.0');
    $this->getJson("/internal/accounts/{$user->id}/summary")->assertUnauthorized();
});

/** An account with one of everything this app keeps for it. */
function accountWithData(string $slug): User
{
    $user = User::factory()->create();
    $folder = Folder::create(['user_id' => $user->id, 'name' => 'Work']);
    $tag = Tag::create(['user_id' => $user->id, 'name' => 'promo']);
    $link = Link::create(['user_id' => $user->id, 'folder_id' => $folder->id, 'slug' => $slug, 'target_url' => 'https://example.com/'.$slug, 'status' => 'active']);
    $link->tags()->attach($tag->id);
    $link->clicks()->create(['clicked_at' => now()]);
    Comment::create(['link_id' => $link->id, 'user_id' => $user->id, 'body' => 'note']);
    WebhookEndpoint::create(['user_id' => $user->id, 'url' => 'https://hooks.example.net/in', 'secret' => 'whsec_test']);
    DB::table('api_idempotency_keys')->insert(['user_id' => $user->id, 'key' => 'k1', 'fingerprint' => str_repeat('a', 64), 'expires_at' => now()->addDay()]);
    // the redirect caches links by slug
    Cache::put('slug:'.$slug, 'cached', 600);

    return $user;
}

function rowsLeftFor(int $userId, bool $withBio = true): array
{
    return array_filter([
        'links' => Link::where('user_id', $userId)->count(),
        'folders' => Folder::where('user_id', $userId)->count(),
        'tags' => Tag::where('user_id', $userId)->count(),
        'webhooks' => WebhookEndpoint::where('user_id', $userId)->count(),
        'keys' => DB::table('api_idempotency_keys')->where('user_id', $userId)->count(),
        'bio' => $withBio && bio_enabled() ? BioPage::where('user_id', $userId)->count() : null,
    ], fn ($n) => $n !== null);
}

test('the purge removes everything the account holds here, reports real counts and can be repeated', function () {
    Storage::fake('public');
    $user = accountWithData('all001');
    $other = accountWithData('all002');
    $link = Link::where('slug', 'all001')->first();
    Storage::disk('public')->put('qrcodes/all001.png', 'x');
    $link->forceFill(['qr_code_path' => 'qrcodes/all001.png'])->saveQuietly();
    BioPage::create(['user_id' => $user->id, 'slug' => 'all-bio', 'title' => 'Bio']);

    hubSigned('DELETE', "/internal/accounts/{$user->id}")->assertOk()
        ->assertJsonPath('data', ['links' => 1, 'folders' => 1, 'tags' => 1, 'bio_pages' => 1]);

    expect(rowsLeftFor($user->id))->each->toBe(0)
        ->and(Comment::where('link_id', $link->id)->count())->toBe(0)
        ->and(DB::table('link_clicks')->where('link_id', $link->id)->count())->toBe(0)
        ->and(DB::table('link_tag')->where('link_id', $link->id)->count())->toBe(0)
        ->and(Cache::has('slug:all001'))->toBeFalse()
        ->and(User::find($user->id))->not->toBeNull()
        // somebody else's data stays
        ->and(rowsLeftFor($other->id, withBio: false))->each->toBe(1);
    Storage::disk('public')->assertMissing('qrcodes/all001.png');

    hubSigned('DELETE', "/internal/accounts/{$user->id}")->assertOk()
        ->assertJsonPath('data', ['links' => 0, 'folders' => 0, 'tags' => 0, 'bio_pages' => 0]);
    expect(rowsLeftFor($other->id, withBio: false))->each->toBe(1);
})->group('bio');

test('the purge needs no user row: links that still point at a vanished account are removed', function () {
    // A database without enforced foreign keys keeps the children of a deleted user: point every row at an
    // id that has no user (the test runs in a transaction, where the check is only put off until commit).
    $user = accountWithData('gone01');
    $ghost = $user->id + 1000;
    DB::statement('PRAGMA defer_foreign_keys = ON');
    foreach (['links', 'folders', 'tags', 'webhook_endpoints', 'api_idempotency_keys'] as $table) {
        DB::table($table)->where('user_id', $user->id)->update(['user_id' => $ghost]);
    }
    expect(User::find($ghost))->toBeNull()->and(rowsLeftFor($ghost)['links'])->toBe(1);

    hubSigned('DELETE', "/internal/accounts/{$ghost}")->assertOk()
        ->assertJsonPath('data.links', 1)->assertJsonPath('data.folders', 1)->assertJsonPath('data.tags', 1);

    expect(rowsLeftFor($ghost))->each->toBe(0)
        ->and(Cache::has('slug:gone01'))->toBeFalse();

    hubSigned('DELETE', "/internal/accounts/{$ghost}")->assertOk()->assertJsonPath('data.links', 0);
});

test('the account.deleted event purges by id too', function () {
    $this->withMiddleware(ValidateCsrfToken::class);
    $user = accountWithData('evt001');
    $other = accountWithData('evt002');

    hubSigned('POST', '/hop/events', ['id' => 'evt-9', 'type' => 'account.deleted', 'data' => ['sub' => (string) $user->id]])->assertOk();

    expect(rowsLeftFor($user->id))->each->toBe(0)
        ->and(rowsLeftFor($other->id, withBio: false))->each->toBe(1);
    // a replay of the same event is harmless
    hubSigned('POST', '/hop/events', ['id' => 'evt-9', 'type' => 'account.deleted', 'data' => ['sub' => (string) $user->id]])->assertOk();
});

test('purging first and deleting the user row after leaves no ownerless link behind', function () {
    // links.user_id is set to null when the row goes: the owner can no longer be found, so the order matters.
    $user = accountWithData('ord001');
    $survivor = Link::create(['user_id' => $user->id, 'slug' => 'ord002', 'target_url' => 'https://example.com/ord2', 'status' => 'active']);

    DB::table('users')->where('id', $user->id)->delete();
    expect($survivor->fresh()->user_id)->toBeNull();
    hubSigned('DELETE', "/internal/accounts/{$user->id}")->assertOk()->assertJsonPath('data.links', 0);
    expect(Link::where('slug', 'ord002')->exists())->toBeTrue(); // unreachable by owner id: why the hub purges first

    $second = accountWithData('ord003');
    hubSigned('DELETE', "/internal/accounts/{$second->id}")->assertOk()->assertJsonPath('data.links', 1);
    DB::table('users')->where('id', $second->id)->delete();
    expect(Link::where('slug', 'ord003')->exists())->toBeFalse();
});

test('the export and the purge cover the saved QR codes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $style = ['fg' => '#000000', 'bg' => '#ffffff', 'dots' => 'square', 'corners' => 'square'];
    QrCode::create(['user_id' => $user->id, 'type' => 'wifi', 'label' => 'Cafe', 'payload' => 'WIFI:T:WPA;S:Cafe;P:made-up-pass;;', 'style' => $style]);
    QrCode::create(['user_id' => $other->id, 'type' => 'text', 'label' => 'Theirs', 'payload' => 'https://example.com/theirs', 'style' => $style]);

    hubSigned('GET', "/internal/accounts/{$user->id}/export")->assertOk()
        ->assertJsonCount(1, 'data.qr_codes')
        ->assertJsonPath('data.qr_codes.0.label', 'Cafe')
        ->assertJsonPath('data.qr_codes.0.content', 'WIFI:T:WPA;S:Cafe;P:made-up-pass;;');

    hubSigned('DELETE', "/internal/accounts/{$user->id}")->assertOk();

    expect(QrCode::where('user_id', $user->id)->count())->toBe(0)->and(QrCode::where('user_id', $other->id)->count())->toBe(1);
});

test('the export skips a saved code that cannot be opened (the app key changed) and says how many', function () {
    $user = User::factory()->create();
    $style = ['fg' => '#000000', 'bg' => '#ffffff', 'dots' => 'square', 'corners' => 'square'];
    QrCode::create(['user_id' => $user->id, 'type' => 'text', 'label' => 'Readable', 'payload' => 'https://example.com/r', 'style' => $style]);
    // a row sealed with another key
    DB::table('qr_codes')->insert(['id' => (string) \Illuminate\Support\Str::ulid(), 'user_id' => $user->id, 'type' => 'wifi', 'label' => 'not-a-sealed-value', 'payload' => 'eyJpdiI6ImJhZCJ9',
        'style' => json_encode($style), 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);

    hubSigned('GET', "/internal/accounts/{$user->id}/export")->assertOk()
        ->assertJsonCount(1, 'data.qr_codes')->assertJsonPath('data.qr_codes.0.label', 'Readable')->assertJsonPath('data.qr_codes_unreadable', 1);
});
