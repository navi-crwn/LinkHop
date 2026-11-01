<?php

use App\Models\Link;
use App\Models\RetiredSlug;
use App\Models\User;

function editorLink(User $user, string $slug, array $attrs = []): Link
{
    return Link::create($attrs + ['user_id' => $user->id, 'slug' => $slug, 'target_url' => 'https://example.com/'.$slug, 'status' => 'active']);
}

test('slug check says free, taken, reserved or malformed, and nothing else', function (string $slug, bool $available, ?string $reason) {
    $alice = User::factory()->create();
    editorLink(User::factory()->create(), 'someone-elses');
    RetiredSlug::retire('retired-one');
    $res = $this->actingAs($alice)->getJson('/links/slug-check?slug='.urlencode($slug))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $res->assertJsonPath('available', $available)->assertJsonPath('reason', $reason);
    // whatever the answer, it carries no id, owner, address or other link data
    expect(array_keys($res->json()))->toBe(['available', 'reason', 'message']);
})->with([
    'free' => ['brand-new', true, null],
    'free in capitals' => ['Brand-New', true, null],
    'taken by another account' => ['someone-elses', false, 'taken'],
    'taken, other case' => ['SOMEONE-ELSES', false, 'taken'],
    'retired' => ['retired-one', false, 'taken'],
    'reserved' => ['api', false, 'reserved'],
    'too short' => ['ab', false, 'short'],
    'too long' => [str_repeat('a', 49), false, 'long'],
    'underscore (the form refuses it)' => ['my_slug', false, 'format'],
    'space' => ['my slug', false, 'format'],
    'empty' => ['', false, null],
]);

test('slug check needs a signed-in user', function () {
    $this->getJson('/links/slug-check?slug=abc')->assertUnauthorized();
});

test('slug check lets the edited link keep its own slug, and only its owner (or an admin) gets that', function () {
    $owner = User::factory()->create();
    $mine = editorLink($owner, 'keep-this');
    $this->actingAs($owner)->getJson('/links/slug-check?slug=keep-this')->assertJsonPath('available', false)->assertJsonPath('reason', 'taken');
    $this->actingAs($owner)->getJson("/links/slug-check?slug=keep-this&link={$mine->id}")->assertJsonPath('available', true);
    $this->actingAs($owner)->getJson("/links/slug-check?slug=KEEP-this&link={$mine->id}")->assertJsonPath('available', true);
    // a stranger naming someone else's link id gets no special treatment
    $this->actingAs(User::factory()->create())->getJson("/links/slug-check?slug=keep-this&link={$mine->id}")->assertJsonPath('available', false);
});

test('a link may keep a reserved or underscore slug it already has when editing', function () {
    $owner = User::factory()->create();
    $legacy = editorLink($owner, 'old_slug_1');
    $this->actingAs($owner)->getJson("/links/slug-check?slug=old_slug_1&link={$legacy->id}")->assertJsonPath('available', true);
});

test('the slug check agrees with what the form accepts', function () {
    $user = User::factory()->create();
    $other = editorLink(User::factory()->create(), 'taken-slug');
    foreach (['taken-slug', 'api', 'ab', 'fine-slug', 'bad_slug'] as $slug) {
        $says = $this->actingAs($user)->getJson('/links/slug-check?slug='.$slug)->json('available');
        $form = $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/x'.$slug, 'slug' => $slug, 'status' => 'active']);
        expect($form->status() === 200)->toBe($says, "slug {$slug}");
    }
});

test('the palette lists only the caller’s own links, 8 at most, newest first', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    foreach (range(1, 10) as $i) {
        editorLink($alice, 'alice-'.$i);
    }
    editorLink($bob, 'bob-secret', ['custom_title' => 'alice-looking title']);
    $res = $this->actingAs($alice)->getJson('/palette/links')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect($res->json('links'))->toHaveCount(8)->and($res->json('links.0.slug'))->toBe('alice-10');
    expect(json_encode($res->json()))->not->toContain('bob-secret');
    $found = $this->actingAs($alice)->getJson('/palette/links?q=alice-3')->json('links');
    expect(collect($found)->pluck('slug')->all())->toBe(['alice-3']);
    expect($this->actingAs($alice)->getJson('/palette/links?q=bob')->json('links'))->toBe([]);
    expect($this->actingAs($alice)->getJson('/palette/links?q=alice-looking')->json('links'))->toBe([]);
});

test('the palette search treats % and _ as plain characters', function () {
    $user = User::factory()->create();
    editorLink($user, 'plain-one');
    editorLink($user, 'percent-x', ['target_url' => 'https://example.com/100%25-real']);
    expect($this->actingAs($user)->getJson('/palette/links?q=%25')->json('links.0.slug'))->toBe('percent-x');
    expect($this->actingAs($user)->getJson('/palette/links?q=_')->json('links'))->toBe([]);
    expect($this->actingAs($user)->getJson('/palette/links?q='.str_repeat('x', 500))->assertOk()->json('links'))->toBe([]);
});

test('palette entries point at the link page and its edit page; the endpoint needs a login', function () {
    $user = User::factory()->create();
    $link = editorLink($user, 'go-here');
    $row = $this->actingAs($user)->getJson('/palette/links?q=go-here')->json('links.0');
    expect($row['url'])->toBe(route('links.show', $link))->and($row['edit_url'])->toBe(route('links.edit', $link));
    auth()->logout();
    $this->getJson('/palette/links')->assertUnauthorized();
});

test('every app page carries the palette and a button for it', function () {
    $user = User::factory()->create();
    $page = $this->actingAs($user)->get('/dashboard')->assertOk();
    $page->assertSee('x-data="commandPalette(', false)->assertSee('role="dialog"', false)->assertSee('aria-modal="true"', false)
        ->assertSee('open-palette', false)->assertSee('Import links from CSV');
    expect(str_replace('\\', '', $page->getContent()))->toContain('palette/links');
    expect(substr_count($page->getContent(), 'x-data="commandPalette('))->toBe(1);
});

test('the edit page and the create modal check the slug as you type', function () {
    $user = User::factory()->create();
    $link = editorLink($user, 'edit-me');
    $this->actingAs($user)->get(route('links.edit', $link))->assertOk()
        ->assertSee('slugField(', false)->assertSee('urlPreview(', false)->assertSee(route('links.slug-check'), false);
    $this->actingAs($user)->get('/dashboard')->assertSee('hopSlugChecker(', false)->assertSee('slugCheck.run(formData.slug)', false);
});

test('copy buttons in the lists give feedback at once and say so when copying fails', function () {
    $user = User::factory()->create();
    editorLink($user, 'copy-me');
    $this->actingAs($user)->get('/links')->assertOk()->assertSee('hopCopy(', false)->assertSee('data-copied="Copied"', false)->assertSee('data-failed="Copy failed"', false);
    $this->actingAs($user)->get('/dashboard')->assertSee('hopCopy(', false);
});
