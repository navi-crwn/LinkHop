<?php

use App\Models\AbuseFlag;
use App\Models\Link;
use App\Models\User;
use Hop\Bio\Models\BioLink;
use Hop\Bio\Models\BioPage;
use Hop\Safety\Models\DomainBlacklist;

function takedown(string $type, int $id, int $userId): AbuseFlag
{
    return AbuseFlag::create(['subject_type' => $type, 'subject_id' => $id, 'user_id' => $userId, 'score' => 100,
        'signals' => ['phishing'], 'action' => AbuseFlag::ACTION_DISABLED, 'status' => AbuseFlag::STATUS_OPEN, 'summary' => 'test']);
}

test('owners cannot re-publish a bio page or re-enable a block taken down by moderation', function () {
    $user = User::factory()->create();
    $page = BioPage::create(['user_id' => $user->id, 'slug' => 'modpage', 'title' => 'P', 'theme' => 'default', 'is_published' => false]);
    $block = BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'B', 'url' => 'https://example.com', 'is_active' => false, 'order' => 0]);
    takedown('bio_page', $page->id, $user->id);
    takedown('bio_link', $block->id, $user->id);

    $this->actingAs($user)->putJson('/bio/modpage', ['is_published' => true])->assertUnprocessable();
    $this->actingAs($user)->postJson("/bio/modpage/links/{$block->id}/toggle")->assertStatus(422);
    $this->actingAs($user)->putJson("/bio/modpage/links/{$block->id}", ['is_active' => true])->assertUnprocessable();
    expect($page->fresh()->is_published)->toBeFalse()->and($block->fresh()->is_active)->toBeFalse();

    // Other edits still work while locked.
    $this->actingAs($user)->putJson('/bio/modpage', ['title' => 'Renamed'])->assertOk();
});

test('owners cannot re-activate a short link taken down by moderation, until an admin restores it', function () {
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'modlink', 'target_url' => 'https://example.com', 'status' => Link::STATUS_INACTIVE]);
    $flag = takedown('link', $link->id, $user->id);
    $payload = ['target_url' => 'https://example.com', 'slug' => 'modlink', 'status' => Link::STATUS_ACTIVE];

    $this->actingAs($user)->put("/links/{$link->id}", $payload)->assertSessionHasErrors('status');
    expect($link->fresh()->status)->toBe(Link::STATUS_INACTIVE);

    // An admin restoring the link from the control center dismisses the finding.
    $flag->update(['status' => AbuseFlag::STATUS_DISMISSED]);
    $link->update(['status' => Link::STATUS_INACTIVE]);
    $this->actingAs($user)->put("/links/{$link->id}", $payload)->assertSessionHasNoErrors();
});

test('an admin unpublishing a bio page locks it until the admin publishes it again', function () {
    $user = User::factory()->create();
    $page = BioPage::create(['user_id' => $user->id, 'slug' => 'adminoff', 'title' => 'P', 'theme' => 'default', 'is_published' => true]);

    // An admin takedown from the control center: unpublished plus a confirmed flag.
    $page->update(['is_published' => false]);
    $flag = AbuseFlag::create(['subject_type' => 'bio_page', 'subject_id' => $page->id, 'user_id' => $user->id, 'score' => 0,
        'signals' => ['admin'], 'action' => AbuseFlag::ACTION_DISABLED, 'status' => AbuseFlag::STATUS_CONFIRMED, 'summary' => 'Unpublished by an admin']);
    $this->actingAs($user)->putJson('/bio/adminoff', ['is_published' => true])->assertUnprocessable();
    expect($page->fresh()->is_published)->toBeFalse();

    // The admin publishes it again: the flag is dismissed and the owner is free.
    $page->update(['is_published' => true]);
    $flag->update(['status' => AbuseFlag::STATUS_DISMISSED]);
    $this->actingAs($user)->putJson('/bio/adminoff', ['is_published' => false])->assertOk();
    $this->actingAs($user)->putJson('/bio/adminoff', ['is_published' => true])->assertOk();
});

test('social handles that expand to a blacklisted host are rejected and scanned', function () {
    DomainBlacklist::create(['domain' => 'phish-host.tk', 'match_type' => 'exact', 'category' => 'phishing']);
    $user = User::factory()->create();
    BioPage::create(['user_id' => $user->id, 'slug' => 'socialpage', 'title' => 'P', 'theme' => 'default', 'is_published' => true]);

    $this->actingAs($user)->putJson('/bio/socialpage', ['social_links' => [['platform' => 'tumblr', 'value' => 'phish-host.tk/login', 'enabled' => true]]])
        ->assertUnprocessable()->assertJsonValidationErrors('social_links.0.value');
    $this->actingAs($user)->putJson('/bio/socialpage', ['social_links' => [['platform' => 'tumblr', 'value' => 'myblog', 'enabled' => true]]])->assertOk();
});

test('a bio page cannot be renamed to a reserved slug', function () {
    $user = User::factory()->create();
    BioPage::create(['user_id' => $user->id, 'slug' => 'normal', 'title' => 'P', 'theme' => 'default', 'is_published' => true]);

    $this->actingAs($user)->putJson('/bio/normal', ['slug' => 'paypal'])->assertUnprocessable()->assertJsonValidationErrors('slug');
    $this->actingAs($user)->putJson('/bio/normal', ['slug' => 'normal', 'title' => 'Same slug is fine'])->assertOk();
});
