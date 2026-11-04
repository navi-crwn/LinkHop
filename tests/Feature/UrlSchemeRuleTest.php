<?php

use App\Models\Link;
use App\Models\User;
use App\Support\LinkImport;
use Hop\Auth\Models\ApiToken;

function importRow(string $destination): array
{
    return ['line' => 2, 'destination' => $destination, 'slug' => '', 'tags' => '', 'folder' => '', 'expires_at' => '', 'max_clicks' => ''];
}

test('an address with another scheme is refused, not turned into https://ftp://...', function (string $address) {
    $user = User::factory()->create();
    $token = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create'])['plain_token']];

    $this->actingAs($user)->postJson('/links', ['target_url' => $address, 'status' => 'active'])->assertStatus(422)->assertJsonValidationErrors('target_url');
    $this->withHeaders($token)->postJson('/api/shorten', ['url' => $address])->assertStatus(422);
    expect(LinkImport::check($user, [importRow($address)])[0]['status'])->toBe('error');
    expect(Link::count())->toBe(0);
})->with([
    'ftp' => 'ftp://files.example.com/a',
    'ftp in capitals' => 'FTP://files.example.com/a',
    'mailto' => 'mailto:someone@example.com',
]);

test('a bare address and an address with a port still get https://', function (string $address, string $stored) {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/links', ['target_url' => $address, 'status' => 'active'])->assertOk();
    expect(Link::value('target_url'))->toBe($stored);
    expect(LinkImport::check($user, [importRow($address)])[0]['destination'])->toBe($stored);
})->with([
    'bare host' => ['example.com/x', 'https://example.com/x'],
    'host and port' => ['example.com:8080/x', 'https://example.com:8080/x'],
    'upper case scheme' => ['HTTP://Example.com/x', 'HTTP://Example.com/x'],
]);
