<?php

use App\Http\Middleware\VerifyHubRequest;
use App\Models\Folder;
use App\Models\Link;
use App\Models\User;
use App\Support\HopSignature;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
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
