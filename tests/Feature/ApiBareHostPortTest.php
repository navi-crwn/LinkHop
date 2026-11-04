<?php

use App\Models\Link;
use App\Models\User;
use Hop\Safety\UrlGuard;

/*
| OP-13: the dashboard API (/api/shorten-web, /api/bio/shorten) read "example.com:8080/x" as a link with the scheme
| "example.com:" and refused it; the web forms read it as a host and a port and add https://. They now agree.
*/

beforeEach(function () {
    app()->instance(UrlGuard::class, new class extends UrlGuard
    {
        protected function resolveHost(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
});

it('takes a bare host with a port in the dashboard API, as the web form does', function (string $typed, string $stored) {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => $typed])->assertCreated();

    expect(Link::where('user_id', $user->id)->value('target_url'))->toBe($stored);
})->with([
    'host:port/path' => ['example.com:8080/x', 'https://example.com:8080/x'],
    'host:port' => ['example.com:8443', 'https://example.com:8443'],
    'plain host' => ['example.com/x', 'https://example.com/x'],
    'already https' => ['https://example.com/x', 'https://example.com/x'],
]);

it('still refuses other schemes in the dashboard API', function (string $typed) {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => $typed])->assertStatus(422);

    expect(Link::count())->toBe(0);
})->with(['javascript:alert(1)', 'ftp://example.com/x', 'mailto:me@example.com', 'data:text/html,hi']);

it('agrees with the web form on what a bare host with a port becomes', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', ['target_url' => 'example.com:8080/x', 'status' => 'active', 'slug' => 'webform'])->assertOk();
    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => 'example.com:8080/x'])->assertCreated();

    expect(Link::pluck('target_url')->unique()->all())->toBe(['https://example.com:8080/x']);
});
