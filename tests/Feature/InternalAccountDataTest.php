<?php

use App\Http\Middleware\VerifyHubRequest;
use App\Models\Folder;
use App\Models\Link;
use App\Models\User;
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
