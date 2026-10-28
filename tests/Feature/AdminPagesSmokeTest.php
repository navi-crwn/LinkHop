<?php

use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use Hop\Bio\Models\BioPage;

function smokeMember(): User
{
    $user = User::factory()->create();
    $folder = Folder::create(['name' => 'Default', 'user_id' => $user->id, 'is_default' => true]);
    $tag = Tag::create(['name' => 'promo', 'user_id' => $user->id]);
    $link = Link::create(['slug' => 'memberlink', 'target_url' => 'https://example.com', 'user_id' => $user->id, 'status' => 'active', 'folder_id' => $folder->id]);
    $link->tags()->attach($tag);

    return $user;
}

test('every member page renders', function (string $path) {
    $this->actingAs(smokeMember())->get($path)->assertOk();
})->with(['/dashboard', '/links', '/analytics', '/folders', '/tags', '/bio', '/settings', '/api-docs', '/changes/short-links']);

test('an admin gets the same member pages, not an admin panel', function (string $path) {
    smokeMember();
    $admin = User::factory()->create(['email' => config('auth.superadmin_email', 'admin@hel.ink')]);
    $this->actingAs($admin)->get($path)->assertOk()->assertDontSee('Admin Control Panel');
})->with(['/dashboard', '/links', '/analytics', '/bio', '/settings']);

test('member link, folder, tag and create pages render', function () {
    $member = smokeMember();
    $link = $member->links()->first();
    $this->actingAs($member);

    $this->get(route('links.show', $link))->assertOk()->assertSee('/memberlink');
    $this->get(route('links.edit', $link))->assertOk()->assertSee('#promo');
    $this->get(route('links.create'))->assertOk();
    $this->get(route('folders.manage', $member->folders()->first()))->assertOk()->assertSee('memberlink');
    $this->get(route('tags.manage', $member->tags()->first()))->assertOk()->assertSee('memberlink');
    $this->get('/folders')->assertOk()->assertSee('/memberlink');
    $this->get('/tags')->assertOk()->assertSee('promo');
});

test('bio analytics page renders for the owner', function () {
    $member = smokeMember();
    $page = BioPage::create(['user_id' => $member->id, 'slug' => 'memberbio', 'title' => 'Member bio', 'is_published' => true]);
    $this->actingAs($member)->get(route('bio.analytics', $page))->assertOk();
});
