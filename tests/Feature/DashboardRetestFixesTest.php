<?php

use App\Models\ActivityLog;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/*
| Fixes from the dashboard retest of 2026-11-08: deleting a folder or tag from its own page landed on a 404, the
| activity log skipped most edits, the Settings cards opened the wrong hub tabs, "1 short links", the QR code of
| an existing link had no page, and an exhausted link was still called "Active".
*/

function retestLink(User $user, array $attributes = []): Link
{
    return Link::create([
        'user_id' => $user->id,
        'slug' => 'rt'.fake()->unique()->numberBetween(1000, 99999),
        'target_url' => 'https://example.com/page',
        'status' => Link::STATUS_ACTIVE,
        ...$attributes,
    ]);
}

function retestEdit(Link $link, array $extra = []): array
{
    return array_merge(['target_url' => $link->target_url, 'status' => 'active', 'slug' => $link->slug, 'folder_id' => $link->folder_id ?? ''], $extra);
}

it('goes back to the folder list, with a message, after a folder is deleted from its own page', function () {
    $user = User::factory()->create();
    $folder = Folder::create(['name' => 'Campaign', 'user_id' => $user->id]);

    $response = $this->actingAs($user)->from(route('folders.manage', $folder))->delete(route('folders.destroy', $folder));

    $response->assertRedirect(route('folders.index'))->assertSessionHas('status');
    expect(Folder::find($folder->id))->toBeNull();
    $this->get(route('folders.manage', $folder))->assertNotFound();
});

it('goes back to the tag list, with a message, after a tag is deleted from its own page', function () {
    $user = User::factory()->create();
    $tag = Tag::create(['name' => 'social', 'user_id' => $user->id]);
    $link = retestLink($user);
    $link->tags()->attach($tag);

    $this->actingAs($user)->from(route('tags.manage', $tag))->delete(route('tags.destroy', $tag))
        ->assertRedirect(route('tags.index'))->assertSessionHas('status');

    expect(Tag::find($tag->id))->toBeNull()->and($link->fresh()->tags)->toHaveCount(0);
});

it('does not describe a missing dashboard page as a missing short link', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get('/folders/999999/manage')->assertNotFound()->getContent();

    expect($html)->toContain('Page not found')->not->toContain('short link')->toContain(route('dashboard'));
});

it('logs every kind of edit, old value to new, without the password itself', function () {
    $user = User::factory()->create();
    $folder = Folder::create(['name' => 'Work', 'user_id' => $user->id]);
    $link = retestLink($user, ['custom_title' => 'Old title']);

    $this->actingAs($user)->put(route('links.update', $link), retestEdit($link, [
        'target_url' => 'https://example.org/new',
        'folder_id' => $folder->id,
        'custom_title' => 'New title',
        'expires_at' => '2030-01-02T03:04',
        'max_clicks' => 25,
        'content_warning' => 'sensitive',
        'warning_reason' => 'graphic',
        'status' => 'inactive',
        'password' => 'hunter22',
        'routing_rules' => [['device' => 'mobile', 'url' => 'https://m.example.org/']],
    ]))->assertSessionHasNoErrors();

    $entry = ActivityLog::where('model', 'Link')->where('model_id', $link->id)->where('action', 'updated')->latest('id')->first();
    expect($entry)->not->toBeNull();
    expect($entry->description)
        ->toContain('destination: https://example.com/page → https://example.org/new')
        ->toContain('folder: none → Work')
        ->toContain('title: Old title → New title')
        ->toContain('expires: never → 2030-01-02 03:04:00')
        ->toContain('click limit: none → 25')
        ->toContain('content warning: none → sensitive')
        ->toContain('status: active → inactive')
        ->toContain('password set')
        ->toContain('routing rules updated')
        ->not->toContain('hunter22');
});

it('logs a password change and a password removal as such', function () {
    $user = User::factory()->create();
    $link = retestLink($user, ['password_hash' => Hash::make('first-one')]);

    $this->actingAs($user)->put(route('links.update', $link), retestEdit($link, ['password' => 'second-one']))->assertSessionHasNoErrors();
    expect(ActivityLog::where('model_id', $link->id)->latest('id')->value('description'))->toContain('password changed')->not->toContain('second-one');

    $this->put(route('links.update', $link), retestEdit($link, ['remove_password' => 1]))->assertSessionHasNoErrors();
    expect(ActivityLog::where('model_id', $link->id)->latest('id')->value('description'))->toContain('password removed');
});

it('writes no activity entry when an edit changes nothing', function () {
    $user = User::factory()->create();
    $link = retestLink($user);

    $this->actingAs($user)->put(route('links.update', $link), retestEdit($link))->assertSessionHasNoErrors();

    expect(ActivityLog::where('model_id', $link->id)->where('action', 'updated')->exists())->toBeFalse();
});

it('shows the activity time with its zone, stored in UTC', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    ActivityLog::log('created', 'Link', 1, 'Created link: abc → https://example.com');

    $html = $this->get(route('settings'))->assertOk()->getContent();

    expect($html)->toMatch('/<time datetime="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z"/')->toContain(' UTC</time>');
});

it('sends the Settings cards to the matching tabs of the hub settings page', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get(route('settings'))->assertOk()->getContent();

    expect($html)->toContain('href="'.hub_url('settings').'#account"')
        ->toContain('href="'.hub_url('settings').'#2fa"')
        ->toContain('href="'.hub_url('settings').'#api"')
        ->toContain('href="'.hub_url('settings').'#backup"')
        ->not->toContain(hub_url('profile'))
        ->not->toContain('#security"')
        ->not->toContain('#data"');
});

it('counts "1 short link" in the singular and keeps the count in a script-updated element', function () {
    $user = User::factory()->create();
    retestLink($user);

    $html = $this->actingAs($user)->get(route('links.index'))->assertOk()->getContent();

    expect($html)->toContain('x-text="countLabel()">1 short link<')->not->toContain('1 short links');
});

it('translates the sidebar, the Settings page and the notice', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->withUnencryptedCookie('hop_lang', 'de')->get(route('settings'))->assertOk()->getContent();

    expect($html)->toContain(trans('Dashboard', [], 'de'))->toContain(trans('My links', [], 'de'))->toContain(trans('Folders', [], 'de'))
        ->toContain(e(trans('Backup & export', [], 'de')))->toContain(trans('Activity log', [], 'de'));
    expect(trans('My links', [], 'de'))->not->toBe('My links');
    expect(trans('Folders', [], 'de'))->not->toBe('Folders');
    expect(trans('Notice', [], 'de'))->not->toBe('Notice');
});

it('shows the QR code of an existing link with downloads and a colour form, and saves new colours', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $link = retestLink($user);

    $html = $this->actingAs($user)->get(route('links.show', $link))->assertOk()->getContent();
    expect($html)->toContain(route('links.qr.download', [$link, 'png']))
        ->toContain(route('links.qr.download', [$link, 'svg']))
        ->toContain('action="'.route('links.qr.update', $link).'"');

    $this->post(route('links.qr.update', $link), ['qr_fg_color' => '#1E40AF', 'qr_bg_color' => '#fef3c7'])
        ->assertRedirect(route('links.show', $link))->assertSessionHas('status');
    $link->refresh();
    expect($link->qr_fg_color)->toBe('#1e40af')->and($link->qr_bg_color)->toBe('#fef3c7')->and($link->qr_code_path)->not->toBeNull();
    Storage::disk('public')->assertExists($link->qr_code_path);
    expect(ActivityLog::where('model_id', $link->id)->latest('id')->value('description'))->toContain('QR colours');

    $this->post(route('links.qr.update', $link), ['qr_fg_color' => 'blue', 'qr_bg_color' => '#ffffff'])->assertSessionHasErrors('qr_fg_color');
    $this->get(route('links.qr.download', [$link, 'svg']))->assertOk()->assertSee('#1e40af', false);
});

it('refuses the QR colour form for a link that belongs to someone else', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $link = retestLink($owner);

    $this->actingAs($other)->post(route('links.qr.update', $link), ['qr_fg_color' => '#000000', 'qr_bg_color' => '#ffffff'])->assertForbidden();
});

it('calls an exhausted link "Limit reached", not "Active", on its analytics and edit pages and in the dashboard total', function () {
    $user = User::factory()->create();
    $spent = retestLink($user, ['max_clicks' => 3, 'clicks' => 3]);
    retestLink($user);

    $this->actingAs($user);
    $analytics = $this->get(route('links.show', $spent))->assertOk()->getContent();
    expect($analytics)->toContain('Limit reached')->not->toContain('chip-green">Active');

    $edit = $this->get(route('links.edit', $spent))->assertOk()->getContent();
    expect($edit)->toContain($spent->short_url.' · Limit reached');

    $dashboard = $this->get(route('dashboard'))->assertOk()->getContent();
    expect($dashboard)->toMatch('/Active<\/dt><dd[^>]*>1</');
});

it('lets a visitor read the short-links notice without signing in', function () {
    $this->get('/changes/short-links')->assertOk()->assertSee('/l/');
});

it('writes the link checker addresses in the /l/ form', function () {
    $user = User::factory()->create();
    $link = retestLink($user, ['slug' => 'checkme1']);

    $this->get('/check?url='.urlencode($link->short_url))->assertOk()->assertSee(public_host().'/l/checkme1');
    $this->get('/check?url=nosuch777')->assertOk()->assertSee(public_host().'/l/nosuch777');
});

it('keeps the support address out of reach of email obfuscation on the public not-found page', function () {
    $html = view('errors.link-missing', ['slug' => 'abc'])->render();

    expect($html)->toMatch('~<!--email_off--><a href="mailto:[^"]+"[^>]*>[^<]+</a><!--/email_off-->~');
});

it('names the account email inside an email_off block in the account menu', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain('<!--email_off-->'.e($user->email).'<!--/email_off-->');
});

it('redirects a deleted link in the bulk flow to a 404 the page treats as already gone', function () {
    $user = User::factory()->create();
    $link = retestLink($user);
    $link->delete();

    $this->actingAs($user)->deleteJson(route('links.destroy', $link->id))->assertNotFound();
});
