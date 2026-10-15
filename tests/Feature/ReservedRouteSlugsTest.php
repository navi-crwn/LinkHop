<?php

use App\Http\Requests\ShortenLinkRequest;
use App\Models\ApiToken;
use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

/** First path segment of every route that has a fixed (non-parameter) first segment. */
function staticRouteSegments(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => strtolower(explode('/', trim($route->uri(), '/'))[0]))
        ->filter(fn ($segment) => $segment !== '' && ! str_contains($segment, '{'))
        ->unique()->values()->all();
}

test('no route segment can be taken as a short link slug', function () {
    $segments = staticRouteSegments();
    expect($segments)->toContain('api-docs', 'analytics', 'up', 'csp-report', 'two-factor-challenge');

    $rules = (new ShortenLinkRequest)->rules();
    foreach ($segments as $segment) {
        foreach ([$segment, strtoupper($segment)] as $slug) {
            $validator = Validator::make(['target_url' => 'https://example.com', 'slug' => $slug], ['slug' => $rules['slug']]);
            expect($validator->fails())->toBeTrue("/{$slug} is an app route but was accepted as a slug");
        }
    }
});

test('guests, members and the API are refused a route segment as slug', function () {
    $this->post('/shorten', ['target_url' => 'https://example.com', 'slug' => 'api-docs'])->assertSessionHasErrors('slug');

    $user = User::factory()->create();
    $this->actingAs($user)->post('/links', ['target_url' => 'https://example.com', 'slug' => 'Analytics', 'status' => 'active'])
        ->assertSessionHasErrors('slug');
    $token = ApiToken::generate($user->id, 'cli')['plain_token'];
    $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/shorten', ['url' => 'https://example.com', 'alias' => 'goodbye'])
        ->assertStatus(422);

    expect(Link::whereIn('slug', ['api-docs', 'analytics', 'goodbye'])->exists())->toBeFalse();
});

test('a bio slug cannot be an app route segment either', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/bio/check-slug?slug=onboarding')->assertJson(['available' => false]);
});

test('editing a link that already has such a slug keeps working', function () {
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'goodbye', 'target_url' => 'https://example.com', 'status' => 'active']);

    $this->actingAs($user)->put("/links/{$link->id}", ['target_url' => 'https://example.org', 'slug' => 'goodbye', 'status' => 'active'])
        ->assertSessionHasNoErrors();
    expect($link->fresh()->target_url)->toBe('https://example.org');
});
