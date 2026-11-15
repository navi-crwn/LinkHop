<?php

use App\Models\Link;
use App\Models\User;

function pickerLink(User $user, string $slug): Link
{
    return Link::create(['slug' => $slug, 'target_url' => 'https://example.com/'.$slug, 'user_id' => $user->id, 'status' => 'active']);
}

test('the analytics page does not put the whole link list in its HTML', function () {
    $user = User::factory()->create();
    foreach (range(1, 40) as $i) {
        pickerLink($user, 'picker-'.$i);
    }

    $html = $this->actingAs($user)->get(route('analytics'))->assertOk()->getContent();

    expect($html)->not->toContain('picker-7')->and($html)->not->toContain('picker-33');
});

test('a link chosen in the address still shows in the picker button, and only if it is yours', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $mine = pickerLink($alice, 'alice-chosen');
    $theirs = pickerLink($bob, 'bob-private');

    $this->actingAs($alice)->get(route('analytics', ['link_id' => $mine->id]))->assertOk()->assertSee('/alice-chosen');
    $other = $this->actingAs($alice)->get(route('analytics', ['link_id' => $theirs->id]))->assertOk();
    expect($other->getContent())->not->toContain('bob-private');
});

test('odd link_id values do not crash the page or match a link by their leading digits', function () {
    $user = User::factory()->create();
    $link = pickerLink($user, 'digits-link');

    foreach (['abc', $link->id.'abc', '-1', '1e3'] as $value) {
        $res = $this->actingAs($user)->get(route('analytics', ['link_id' => $value]))->assertOk();
        expect($res->getContent())->not->toContain('digits-link', "link_id={$value}");
    }
});

test('the picker searches through its own endpoint: own links only, a few at a time, signed in only', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    foreach (range(1, 12) as $i) {
        pickerLink($alice, 'search-'.$i);
    }
    pickerLink($bob, 'search-bob');

    $all = $this->actingAs($alice)->getJson(route('analytics.links'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect($all->json('links'))->toHaveCount(8);
    $found = $this->actingAs($alice)->getJson(route('analytics.links', ['q' => 'search-1']))->json('links');
    expect(collect($found)->pluck('slug')->all())->not->toContain('search-bob');
    expect(json_encode($this->actingAs($alice)->getJson(route('analytics.links', ['q' => 'bob']))->json()))->not->toContain('search-bob');
});

test('the picker endpoint turns a guest away', function () {
    $this->getJson(route('analytics.links'))->assertUnauthorized();
});
