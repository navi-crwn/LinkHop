<?php

use App\Models\Link;
use App\Models\User;
use Hop\Auth\Models\ApiToken;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

function idemHeaders(User $user, ?string $key = null): array
{
    $headers = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create'])['plain_token']];

    return $key === null ? $headers : $headers + ['Idempotency-Key' => $key];
}

test('the same key with the same body returns the first answer and makes no second link', function () {
    $user = User::factory()->create();
    $first = $this->withHeaders(idemHeaders($user, 'key-1'))->postJson('/api/shorten', ['url' => 'https://example.com/one', 'alias' => 'idem-one'])->assertCreated();
    expect($first->headers->has('Idempotent-Replayed'))->toBeFalse();
    $again = $this->withHeaders(idemHeaders($user, 'key-1'))->postJson('/api/shorten', ['url' => 'https://example.com/one', 'alias' => 'idem-one'])
        ->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertHeader('Idempotency-Key', 'key-1');
    expect($again->json())->toBe($first->json())->and(Link::where('user_id', $user->id)->count())->toBe(1);
});

test('a retry without a custom alias does not make a second random link', function () {
    $user = User::factory()->create();
    $a = $this->withHeaders(idemHeaders($user, 'key-2'))->postJson('/api/shorten', ['url' => 'https://example.com/two'])->assertCreated();
    $b = $this->withHeaders(idemHeaders($user, 'key-2'))->postJson('/api/shorten', ['url' => 'https://example.com/two'])->assertCreated();
    expect($b->json('data.slug'))->toBe($a->json('data.slug'))->and(Link::count())->toBe(1);
});

test('the order of the fields does not make it another request', function () {
    $user = User::factory()->create();
    $this->withHeaders(idemHeaders($user, 'key-3'))->postJson('/api/shorten', ['url' => 'https://example.com/three', 'alias' => 'three-x'])->assertCreated();
    $this->withHeaders(idemHeaders($user, 'key-3'))->postJson('/api/shorten', ['alias' => 'three-x', 'url' => 'https://example.com/three'])
        ->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    expect(Link::count())->toBe(1);
});

test('the same key with another body is refused with 422 and creates nothing', function () {
    $user = User::factory()->create();
    $this->withHeaders(idemHeaders($user, 'key-4'))->postJson('/api/shorten', ['url' => 'https://example.com/a'])->assertCreated();
    $this->withHeaders(idemHeaders($user, 'key-4'))->postJson('/api/shorten', ['url' => 'https://example.com/b'])
        ->assertStatus(422)->assertJsonPath('error', 'Idempotency Key Reuse');
    expect(Link::count())->toBe(1);
});

test('a key belongs to the account: another account can use the same key', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $this->withHeaders(idemHeaders($alice, 'shared'))->postJson('/api/shorten', ['url' => 'https://example.com/alice'])->assertCreated();
    $res = $this->withHeaders(idemHeaders($bob, 'shared'))->postJson('/api/shorten', ['url' => 'https://example.com/bob'])->assertCreated();
    expect($res->headers->has('Idempotent-Replayed'))->toBeFalse()->and(Link::count())->toBe(2);
});

test('without a key every request makes a link, as before', function () {
    $user = User::factory()->create();
    $this->withHeaders(idemHeaders($user))->postJson('/api/shorten', ['url' => 'https://example.com/x'])->assertCreated();
    $this->withHeaders(idemHeaders($user))->postJson('/api/shorten', ['url' => 'https://example.com/x'])->assertCreated();
    expect(Link::count())->toBe(2)->and(DB::table('api_idempotency_keys')->count())->toBe(0);
});

test('a key is kept for 24 hours, then the same key makes a new link', function () {
    $user = User::factory()->create();
    $this->withHeaders(idemHeaders($user, 'key-5'))->postJson('/api/shorten', ['url' => 'https://example.com/five', 'alias' => 'five-a'])->assertCreated();
    $this->travel(23)->hours();
    $this->withHeaders(idemHeaders($user, 'key-5'))->postJson('/api/shorten', ['url' => 'https://example.com/five', 'alias' => 'five-a'])->assertHeader('Idempotent-Replayed', 'true');
    $this->travel(2)->hours();
    $res = $this->withHeaders(idemHeaders($user, 'key-5'))->postJson('/api/shorten', ['url' => 'https://example.com/other', 'alias' => 'five-b'])->assertCreated();
    expect($res->headers->has('Idempotent-Replayed'))->toBeFalse()->and(Link::count())->toBe(2)->and(DB::table('api_idempotency_keys')->count())->toBe(1);
});

test('a failed request leaves nothing behind, so it can be sent again with the same key', function () {
    $user = User::factory()->create();
    $this->withHeaders(idemHeaders($user, 'key-6'))->postJson('/api/shorten', ['url' => 'not a url'])->assertStatus(422);
    expect(DB::table('api_idempotency_keys')->count())->toBe(0);
    $this->withHeaders(idemHeaders($user, 'key-6'))->postJson('/api/shorten', ['url' => 'https://example.com/fixed'])->assertCreated();
    expect(Link::count())->toBe(1);
});

test('a request that is still running answers 409 to the same key', function () {
    $user = User::factory()->create();
    DB::table('api_idempotency_keys')->insert([
        'user_id' => $user->id, 'key' => 'busy', 'status_code' => null, 'response_body' => null, 'expires_at' => now()->addDay(), 'created_at' => now(),
        // the fingerprint the request below will have
        'fingerprint' => hash('sha256', "POST\napi/shorten\n".json_encode(['url' => 'https://example.com/busy'])),
    ]);
    $this->withHeaders(idemHeaders($user, 'busy'))->postJson('/api/shorten', ['url' => 'https://example.com/busy'])
        ->assertStatus(409)->assertHeader('Retry-After', '1');
    expect(Link::count())->toBe(0);
});

test('a key that is empty, too long or has spaces is refused', function (string $key) {
    $user = User::factory()->create();
    $this->withHeaders(idemHeaders($user, $key))->postJson('/api/shorten', ['url' => 'https://example.com/k'])->assertStatus(422)->assertJsonPath('error', 'Invalid Idempotency Key');
    expect(Link::count())->toBe(0);
})->with(['two words' => 'has space', 'too long' => str_repeat('a', 256), 'control' => "tab\tkey"]);

test('the same key on a token without the create scope does nothing', function () {
    $user = User::factory()->create();
    $headers = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:read'])['plain_token'], 'Idempotency-Key' => 'k'];
    $this->withHeaders($headers)->postJson('/api/shorten', ['url' => 'https://example.com/n'])->assertForbidden();
    expect(DB::table('api_idempotency_keys')->count())->toBe(0);
});

test('two requests cannot both take one key: the unique index decides', function () {
    $user = User::factory()->create();
    $row = ['user_id' => $user->id, 'key' => 'dup', 'fingerprint' => str_repeat('a', 64), 'expires_at' => now()->addDay(), 'created_at' => now()];
    DB::table('api_idempotency_keys')->insert($row);
    expect(fn () => DB::table('api_idempotency_keys')->insert($row))->toThrow(UniqueConstraintViolationException::class);
});

test('idempotency:prune removes the expired keys only', function () {
    $user = User::factory()->create();
    $base = ['user_id' => $user->id, 'fingerprint' => str_repeat('b', 64), 'created_at' => now()];
    DB::table('api_idempotency_keys')->insert([$base + ['key' => 'old', 'expires_at' => now()->subMinute()], $base + ['key' => 'new', 'expires_at' => now()->addHour()]]);
    $this->artisan('idempotency:prune')->assertSuccessful();
    expect(DB::table('api_idempotency_keys')->pluck('key')->all())->toBe(['new']);
});
