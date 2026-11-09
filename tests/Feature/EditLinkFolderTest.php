<?php

use App\Models\Folder;
use App\Models\Link;
use App\Models\User;

// The folder on the edit form is optional. Before, the select was `required` with no empty choice:
// a person without folders could not save at all, and saving an unfiled link moved it into the first folder.

function editFolder(User $user, string $name): Folder
{
    return Folder::create(['name' => $name, 'user_id' => $user->id]);
}

function editLink(User $user, ?int $folderId): Link
{
    return Link::create(['slug' => 'edit'.$user->id, 'target_url' => 'https://example.com', 'user_id' => $user->id, 'status' => 'active', 'folder_id' => $folderId]);
}

function editPayload(Link $link, array $extra = []): array
{
    return array_merge(['target_url' => $link->target_url, 'status' => 'active', 'slug' => $link->slug, 'custom_title' => 'Renamed'], $extra);
}

test('the edit form offers "No folder", selected for an unfiled link, and does not require a folder', function () {
    $user = User::factory()->create();
    editFolder($user, 'Work');
    $link = editLink($user, null);

    $html = $this->actingAs($user)->get(route('links.edit', $link))->assertOk()->getContent();

    expect($html)->toContain('<option value="" selected>No folder</option>')
        ->and($html)->not->toMatch('/<select id="folder_id"[^>]*required/');
});

test('saving an unfiled link keeps it unfiled', function () {
    $user = User::factory()->create();
    editFolder($user, 'Work');
    $link = editLink($user, null);

    $this->actingAs($user)->put(route('links.update', $link), editPayload($link, ['folder_id' => '']))->assertSessionHasNoErrors();

    expect($link->fresh()->folder_id)->toBeNull()
        ->and($link->fresh()->custom_title)->toBe('Renamed');
});

test('a person with no folders can save a link', function () {
    $user = User::factory()->create();
    $link = editLink($user, null);

    $this->actingAs($user)->put(route('links.update', $link), editPayload($link, ['folder_id' => '']))->assertSessionHasNoErrors();

    expect($link->fresh()->custom_title)->toBe('Renamed');
});

test('a filed link keeps its folder, and can be moved or unfiled on purpose', function () {
    $user = User::factory()->create();
    [$a, $b] = [editFolder($user, 'Alpha'), editFolder($user, 'Beta')];
    $link = editLink($user, $b->id);

    $html = $this->actingAs($user)->get(route('links.edit', $link))->getContent();
    expect($html)->toContain('<option value="'.$b->id.'" selected>');

    $this->put(route('links.update', $link), editPayload($link, ['folder_id' => $a->id]))->assertSessionHasNoErrors();
    expect($link->fresh()->folder_id)->toBe($a->id);

    $this->put(route('links.update', $link->fresh()), editPayload($link->fresh(), ['folder_id' => '']))->assertSessionHasNoErrors();
    expect($link->fresh()->folder_id)->toBeNull();
});
