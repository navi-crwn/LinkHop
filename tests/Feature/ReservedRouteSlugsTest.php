<?php

use App\Http\Requests\ManageLinkRequest;
use App\Models\Link;
use App\Models\User;
use App\Support\ReservedSlugs;
use Hop\Auth\Models\ApiToken;
use Illuminate\Support\Facades\Validator;

test('the HOP path prefixes and the configured words are reserved, app route segments are not', function () {
    $rules = (new ManageLinkRequest)->rules();
    $refused = fn (string $slug) => Validator::make(['target_url' => 'https://example.com', 'slug' => $slug], ['slug' => $rules['slug']])->fails();

    foreach (['l', 'b', 'api', 'L', 'API', 'admin', 'paypal', 'helink'] as $slug) {
        expect(ReservedSlugs::isReserved($slug))->toBeTrue("{$slug} should be reserved")
            ->and($refused($slug))->toBeTrue("{$slug} was accepted as a slug");
    }
    // Short links live under /l/, so hel.ink/l/analytics cannot collide with the /analytics page any more.
    foreach (['analytics', 'api-docs', 'goodbye', 'onboarding'] as $slug) {
        expect(ReservedSlugs::isReserved($slug))->toBeFalse("{$slug} need not be reserved");
    }
});

test('members and the API are refused a reserved word as slug', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/links', ['target_url' => 'https://example.com', 'slug' => 'Admin', 'status' => 'active'])
        ->assertSessionHasErrors('slug');
    $token = ApiToken::generate($user->id, 'cli')['plain_token'];
    $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/shorten', ['url' => 'https://example.com', 'alias' => 'b'])
        ->assertStatus(422);

    expect(Link::whereIn('slug', ['api', 'admin', 'b'])->exists())->toBeFalse();
});

test('a bio slug cannot be a reserved word either', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/bio/check-slug?slug=paypal')->assertJson(['available' => false]);
})->group('bio');

test('a slug that is also an app route segment opens under /l/ and can be edited', function () {
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'goodbye', 'target_url' => 'https://example.com', 'status' => 'active']);

    $this->get('/l/goodbye')->assertRedirect('https://example.com');
    $this->actingAs($user)->put("/links/{$link->id}", ['target_url' => 'https://example.org', 'slug' => 'goodbye', 'status' => 'active'])
        ->assertSessionHasNoErrors();
    expect($link->fresh()->target_url)->toBe('https://example.org');
});

test('the words the other HOP apps and tools use are reserved for new slugs, and an existing link that has one is left alone', function () {
    $words = ['qr', 'wifi', 'vcard', 'pay', 'split', 'send', 'secret', 'embargo'];
    $rules = (new ManageLinkRequest)->rules();
    foreach ($words as $word) {
        expect(ReservedSlugs::isReserved($word))->toBeTrue("{$word} should be reserved")
            ->and(ReservedSlugs::isReserved(strtoupper($word)))->toBeTrue()
            ->and(Validator::make(['target_url' => 'https://example.com', 'slug' => $word], ['slug' => $rules['slug']])->fails())->toBeTrue("{$word} was accepted as a new slug");
    }

    // A link made before the word was reserved keeps opening, and can be edited while it keeps its slug.
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'secret', 'target_url' => 'https://example.com', 'status' => 'active']);
    $this->get('/l/secret')->assertRedirect('https://example.com');
    $this->get('/secret')->assertRedirect();
    $this->actingAs($user)->put("/links/{$link->id}", ['target_url' => 'https://example.org', 'slug' => 'secret', 'status' => 'active'])
        ->assertSessionHasNoErrors();
    expect($link->fresh()->target_url)->toBe('https://example.org');
    // Changing it to another reserved word is refused.
    $this->actingAs($user)->put("/links/{$link->id}", ['target_url' => 'https://example.org', 'slug' => 'embargo', 'status' => 'active'])
        ->assertSessionHasErrors('slug');
});

test('every app in the family manifest, and PayHop by name, is a reserved username and a reserved slug', function () {
    $apps = collect(\Hop\Ui\Assets::manifest()['apps'])->pluck('slug');
    expect($apps)->toContain('payhop');

    foreach ($apps->push('pay') as $slug) {
        expect(in_array($slug, config('hop.reserved_usernames'), true))->toBeTrue("{$slug} is not a reserved username");
    }
    foreach (['payhop', 'pay'] as $slug) {
        expect(ReservedSlugs::isReserved($slug))->toBeTrue("{$slug} is not a reserved slug");
    }
    expect(config('hop.reserved_username_prefixes'))->toContain('payhop-');
});
