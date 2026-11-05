<?php

use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use Hop\Bio\Models\BioPage;

/*
| "?q[]=x" makes the value an array. A signed-in page treats a value it cannot use as not given: it answers as usual,
| and never with a 500.
*/

function arUser(): User
{
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'arraylink', 'target_url' => 'https://example.com/array', 'status' => 'active']);
    if (config('bio.enabled')) {
        BioPage::create(['user_id' => $user->id, 'slug' => 'arraypage', 'title' => 'x', 'theme' => 'default']);
    }

    return $user;
}

test('an array-valued filter on a signed-in page counts as not given', function (string $url) {
    $this->actingAs(arUser())->get($url)->assertOk();
})->with([
    '/api/links?query[]=x', '/dashboard?q[]=x', '/dashboard?sort[]=x', '/links?q[]=x', '/links?sort[]=x',
    '/analytics?link_id[]=x', '/analytics?country[]=x', '/analytics?device[]=x',
    '/settings?activity_from[]=x', '/settings?activity_to[]=x',
]);

test('the folder and tag pages take an array-valued search too', function () {
    $user = arUser();
    $folder = Folder::create(['user_id' => $user->id, 'name' => 'f']);
    $tag = Tag::create(['user_id' => $user->id, 'name' => 't']);
    $this->actingAs($user)->get(route('folders.manage', $folder).'?q[]=x')->assertOk();
    $this->actingAs($user)->get(route('tags.manage', $tag).'?q[]=x')->assertOk();
});

test('the filters still work with a normal value', function () {
    $user = arUser();
    Link::create(['user_id' => $user->id, 'slug' => 'otherlink', 'target_url' => 'https://example.org/other', 'status' => 'active']);
    expect($this->actingAs($user)->getJson('/api/links?query=arraylink')->json())->toHaveCount(1);
    $this->actingAs($user)->get('/links?q=arraylink')->assertOk()->assertSee('arraylink')->assertDontSee('otherlink');
    $this->actingAs($user)->get('/settings?activity_from=2026-01-02')->assertOk()->assertSee('value="2026-01-02"', false);
});

test('array-valued fields of a signed-in POST are not a 500', function (string $url, array $body) {
    expect($this->actingAs(arUser())->post($url, $body)->getStatusCode())->toBeLessThan(500);
})->with([
    ['/settings/export', ['min_clicks' => ['x']]],
    ['/settings/export', ['folder_id' => ['x']]],
    ['/settings/export', ['tag_id' => ['x']]],
    ['/settings/export', ['sort_by' => ['x']]],
    ['/settings/export', ['date_from' => ['x']]],
    ['/settings/export-analytics', ['link_id' => ['x']]],
    ['/settings/export-analytics', ['date_from' => ['x']]],
    ['/api/utils/fetch-title', ['url' => ['x']]],
    ['/links/fetch-og', ['url' => ['x']]],
    ['/links', ['target_url' => ['x'], 'status' => 'active']],
    ['/links', ['target_url' => 'https://example.com/a', 'status' => 'active', 'fallback_url' => ['x']]],
    ['/links', ['target_url' => 'https://example.com/a', 'status' => 'active', 'starts_at' => ['x'], 'expires_at' => ['x']]],
    ['/links', ['target_url' => 'https://example.com/a', 'status' => 'active', 'routing_rules' => 'x', 'ab_variants' => 'x', 'app_links' => 'x']],
    ['/links', ['target_url' => 'https://example.com/a', 'status' => 'active', 'warning_reason' => ['x'], 'custom_image_url' => ['x'], 'comment' => ['x']]],
    ['/shorten', ['target_url' => ['x']]],
    ['/bio', ['slug' => ['x'], 'title' => ['x']]],
    ['/bio/arraypage', ['_method' => 'PUT', 'slug' => ['x'], 'title' => ['x']]],
    ['/api/shorten-web', ['url' => ['x']]],
]);
