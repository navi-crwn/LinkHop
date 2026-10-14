<?php

use App\Models\AbuseReport;
use App\Models\ActivityLog;
use App\Models\BioClick;
use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\Link;
use App\Models\LoginHistory;
use App\Models\User;
use App\Support\CspReport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function privacyPage(array $attrs = []): BioPage
{
    return BioPage::create(array_merge([
        'user_id' => User::factory()->create()->id,
        'slug' => 'pp'.strtolower(Str::random(6)),
        'title' => 'Page',
        'theme' => 'default',
        'is_published' => true,
    ], $attrs));
}

test('privacy:prune deletes logs older than the policy allows and keeps recent ones', function () {
    $page = privacyPage();
    $block = BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'A', 'url' => 'https://example.com', 'is_active' => true, 'order' => 0]);
    $old = BioClick::create(['bio_link_id' => $block->id, 'clicked_at' => now()->subDays(31)]);
    $recent = BioClick::create(['bio_link_id' => $block->id, 'clicked_at' => now()->subDays(29)]);
    $user = $page->user;
    $oldLogin = LoginHistory::create(['user_id' => $user->id, 'logged_in_at' => now()->subDays(91)]);
    $newLogin = LoginHistory::create(['user_id' => $user->id, 'logged_in_at' => now()->subDay()]);
    $oldActivity = ActivityLog::create(['user_id' => $user->id, 'action' => 'created', 'model' => 'Link', 'description' => 'x']);
    $oldActivity->forceFill(['created_at' => now()->subDays(91)])->saveQuietly();
    $closed = AbuseReport::create(['reason' => 'spam', 'status' => 'resolved', 'ip_address' => '203.0.113.9']);
    $closed->forceFill(['updated_at' => now()->subDays(181), 'created_at' => now()->subDays(200)])->saveQuietly();
    $open = AbuseReport::create(['reason' => 'spam', 'status' => 'open', 'ip_address' => '203.0.113.10']);
    $open->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();

    $this->artisan('privacy:prune')->assertSuccessful();

    expect(BioClick::find($old->id))->toBeNull()
        ->and(BioClick::find($recent->id))->not->toBeNull()
        ->and(LoginHistory::find($oldLogin->id))->toBeNull()
        ->and(LoginHistory::find($newLogin->id))->not->toBeNull()
        ->and(ActivityLog::find($oldActivity->id))->toBeNull()
        ->and(AbuseReport::find($closed->id))->toBeNull()
        ->and(AbuseReport::find($open->id))->not->toBeNull()
        ->and(AbuseReport::find($open->id)->ip_address)->toBeNull();
});

test('the link checker does not reveal where an inactive, expired or suspended link points', function () {
    Link::create(['slug' => 'offline1', 'target_url' => 'https://example.com/off', 'status' => 'disabled']);
    Link::create(['slug' => 'expired1', 'target_url' => 'https://example.com/exp', 'status' => 'active', 'expires_at' => now()->subDay()]);
    $owner = User::factory()->create(['status' => User::STATUS_SUSPENDED]);
    Link::create(['slug' => 'suspnd1', 'target_url' => 'https://example.com/sus', 'status' => 'active', 'user_id' => $owner->id]);

    $this->get('/check?url=offline1')->assertOk()->assertSee('no longer works')->assertDontSee('example.com/off');
    $this->get('/check?url=expired1')->assertOk()->assertDontSee('example.com/exp');
    $this->get('/check?url=suspnd1')->assertOk()->assertDontSee('example.com/sus');
});

test('deleting an account removes the files uploaded for its bio pages', function () {
    Storage::fake('public');
    Storage::disk('public')->put('avatars/mine.png', 'x');
    Storage::disk('public')->put('bio-thumbnails/thumb.png', 'x');
    $page = privacyPage(['avatar_url' => 'avatars/mine.png']);
    BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'A', 'url' => 'https://example.com', 'thumbnail_url' => '/storage/bio-thumbnails/thumb.png', 'is_active' => true, 'order' => 0]);

    $this->actingAs($page->user)->delete('/profile', ['password' => 'password'])->assertRedirect();

    expect(BioPage::find($page->id))->toBeNull();
    Storage::disk('public')->assertMissing('avatars/mine.png');
    Storage::disk('public')->assertMissing('bio-thumbnails/thumb.png');
});

test('deleting a block removes its image unless another block still uses it', function () {
    Storage::fake('public');
    Storage::disk('public')->put('bio-images/only.png', 'x');
    Storage::disk('public')->put('bio-images/shared.png', 'x');
    $page = privacyPage();
    $only = BioLink::create(['bio_page_id' => $page->id, 'type' => 'image', 'content' => '/storage/bio-images/only.png', 'is_active' => true, 'order' => 0]);
    $a = BioLink::create(['bio_page_id' => $page->id, 'type' => 'image', 'content' => '/storage/bio-images/shared.png', 'is_active' => true, 'order' => 1]);
    BioLink::create(['bio_page_id' => $page->id, 'type' => 'image', 'content' => '/storage/bio-images/shared.png', 'is_active' => true, 'order' => 2]);

    $this->actingAs($page->user)->deleteJson("/bio/{$page->slug}/links/{$only->id}")->assertOk();
    $this->actingAs($page->user)->deleteJson("/bio/{$page->slug}/links/{$a->id}")->assertOk();

    Storage::disk('public')->assertMissing('bio-images/only.png');
    Storage::disk('public')->assertExists('bio-images/shared.png');
});

test('the account export contains bio pages with their blocks and no secrets', function () {
    $page = privacyPage(['password_enabled' => true, 'password' => bcrypt('secret')]);
    BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'My block', 'url' => 'https://example.com', 'is_active' => true, 'order' => 0]);
    LoginHistory::create(['user_id' => $page->user_id, 'logged_in_at' => now(), 'country' => 'ID']);

    $response = $this->actingAs($page->user)->post('/settings/export-account')->assertOk();
    $data = json_decode($response->streamedContent(), true);

    expect($data['account']['email'])->toBe($page->user->email)
        ->and($data['bio_pages'][0]['slug'])->toBe($page->slug)
        ->and($data['bio_pages'][0]['blocks'][0]['title'])->toBe('My block')
        ->and($data['login_history'][0]['country'])->toBe('ID')
        ->and($response->streamedContent())->not->toContain('$2y$');
});

test('csp reports never log tokens, query strings or emails', function () {
    $clean = CspReport::sanitize([
        'document-uri' => 'https://hel.ink/reset-password/abc123def456ghi789jkl012mno?email=a%40b.c',
        'blocked-uri' => 'inline',
        'source-file' => 'https://hel.ink/email/verify/5/0123456789abcdef0123456789abcdef?expires=1&signature=xyz',
        'violated-directive' => 'script-src-attr',
        'original-policy' => 'drop me',
    ]);

    expect($clean['document-uri'])->toBe('https://hel.ink/reset-password/[token]')
        ->and($clean['source-file'])->toBe('https://hel.ink/email/verify/5/[token]')
        ->and($clean['blocked-uri'])->toBe('inline')
        ->and($clean)->not->toHaveKey('original-policy');
});
