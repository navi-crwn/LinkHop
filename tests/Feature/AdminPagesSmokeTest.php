<?php

use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;

function smokeAdmin(): User
{
    return User::factory()->create(['email' => config('auth.superadmin_email', 'admin@hel.ink')]);
}

function smokeMember(): User
{
    $user = User::factory()->create();
    $folder = Folder::create(['name' => 'Default', 'user_id' => $user->id, 'is_default' => true]);
    $tag = Tag::create(['name' => 'promo', 'user_id' => $user->id]);
    $link = Link::create(['slug' => 'memberlink', 'target_url' => 'https://example.com', 'user_id' => $user->id, 'status' => 'active', 'folder_id' => $folder->id]);
    $link->tags()->attach($tag);

    return $user;
}

test('every admin page renders for the superadmin', function (string $path) {
    smokeMember();
    $this->actingAs(smokeAdmin())->get($path)->assertOk();
})->with([
    '/admin/abuse', '/admin/analytics/global', '/admin/bio', '/admin/bio/monitor',
    '/admin/domain-blacklist', '/admin/geoip-monitor', '/admin/ip-bans', '/admin/ip-watchlist',
    '/admin/links', '/admin/links/my', '/admin/proxy-monitor', '/admin/seo', '/admin/users',
    '/dashboard', '/profile', '/settings',
]);

test('admin can inspect a user with links, folders and tags', function () {
    $member = smokeMember();
    $this->actingAs(smokeAdmin())->get(route('admin.users.inspect', $member))
        ->assertOk()
        ->assertSee('memberlink');
});

test('every member page renders', function (string $path) {
    $this->actingAs(smokeMember())->get($path)->assertOk();
})->with(['/dashboard', '/links', '/analytics', '/folders', '/tags', '/bio', '/profile', '/settings']);
