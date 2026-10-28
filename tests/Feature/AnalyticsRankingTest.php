<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;

function rankedLink(User $user, string $slug, array $attrs = []): Link
{
    $link = Link::create(array_merge(['slug' => $slug, 'target_url' => 'https://example.com/'.$slug, 'user_id' => $user->id, 'status' => 'active', 'clicks' => 50], $attrs));
    LinkClick::create(['link_id' => $link->id, 'country' => 'ID', 'clicked_at' => now()->subHour()]);

    return $link;
}

test('live and ended scopes split links by whether they still resolve', function () {
    $user = User::factory()->create();
    $live = rankedLink($user, 'livelink');
    $expired = rankedLink($user, 'oldlink', ['expires_at' => now()->subDay()]);
    $disabled = rankedLink($user, 'offlink', ['status' => 'inactive']);

    expect(Link::live()->pluck('id')->all())->toBe([$live->id])
        ->and(Link::ended()->pluck('id')->sort()->values()->all())->toBe([$expired->id, $disabled->id]);
});

test('top links rank only live links and expired ones are listed apart', function () {
    $user = User::factory()->create();
    rankedLink($user, 'livelink');
    rankedLink($user, 'oldlink', ['expires_at' => now()->subDay(), 'clicks' => 900]);

    $mine = $this->actingAs($user)->get(route('analytics'))->assertOk();
    expect($mine->viewData('topLinks')->pluck('link.slug')->all())->toBe(['livelink'])
        ->and($mine->viewData('endedLinks')->pluck('link.slug')->all())->toBe(['oldlink']);
});
