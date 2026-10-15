<?php

use App\Models\BioPage;
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

test('global links filters do not render every user or tag and still filter', function () {
    $member = smokeMember();
    $other = User::factory()->create(['name' => 'Someone Else']);
    Link::create(['slug' => 'otherlink', 'target_url' => 'https://example.org', 'user_id' => $other->id, 'status' => 'active']);
    $admin = smokeAdmin();

    $this->actingAs($admin)->get('/admin/links')->assertOk()
        ->assertSee('otherlink')->assertDontSee('<option value="'.$other->id.'"', false);

    $this->actingAs($admin)->get('/admin/links?user_id='.$member->id)->assertOk()
        ->assertSee('memberlink')->assertDontSee('otherlink');
    $this->actingAs($admin)->get('/admin/links?tag=promo')->assertOk()
        ->assertSee('memberlink')->assertDontSee('otherlink');

    $this->actingAs($admin)->getJson(route('admin.users.lookup', ['q' => 'Someone']))
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $other->id);
});

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

test('admin bio detail page renders', function () {
    $member = smokeMember();
    $page = BioPage::create(['user_id' => $member->id, 'slug' => 'memberbio', 'title' => 'Member bio', 'is_published' => true]);
    $this->actingAs(smokeAdmin())->get(route('admin.bio.show', $page))->assertOk()->assertSee('Member bio');
    $this->actingAs($member)->get(route('bio.analytics', $page))->assertOk();
});
