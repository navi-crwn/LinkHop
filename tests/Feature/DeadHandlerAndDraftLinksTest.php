<?php

use App\Models\User;
use App\Services\NotificationService;
use Hop\Bio\Models\BioPage;

/*
| What the code seems to promise, it does. No exception handler class that is never used and no mail for a 500 that is
| never sent. A page in draft has no public address yet, so nothing offers one.
*/

it('has no exception handler class besides the one in use', function () {
    expect(file_exists(app_path('Exceptions/Handler.php')))->toBeFalse()
        ->and(method_exists(NotificationService::class, 'notifyCriticalException'))->toBeFalse();
});

function dpPage(User $user, bool $published): BioPage
{
    return BioPage::create(['user_id' => $user->id, 'slug' => $published ? 'livepage' : 'draftpage', 'title' => 'x', 'theme' => 'default', 'is_published' => $published]);
}

it('does not link a draft to its public address', function () {
    $user = User::factory()->create();
    dpPage($user, false);
    $list = $this->actingAs($user)->get('/bio')->assertOk()->getContent();
    expect($list)->not->toContain('href="'.public_url('b/draftpage').'"')->toContain('/b/draftpage');
    $stats = $this->actingAs($user)->get('/bio/draftpage/analytics')->assertOk()->getContent();
    expect($stats)->not->toContain('href="'.public_url('b/draftpage').'"')
        ->not->toContain('href="'.public_url('b/draftpage/qr').'"')
        ->not->toContain('View live');
    $editor = $this->actingAs($user)->get('/bio/draftpage/edit')->assertOk()->getContent();
    expect($editor)->not->toContain(":href=\"'".public_url('b')."/' + bioPage.slug\"");
})->group('bio');

it('keeps the public links of a published page', function () {
    $user = User::factory()->create();
    dpPage($user, true);
    expect($this->actingAs($user)->get('/bio')->assertOk()->getContent())->toContain('href="'.public_url('b/livepage').'"');
    expect($this->actingAs($user)->get('/bio/livepage/analytics')->assertOk()->getContent())
        ->toContain('href="'.public_url('b/livepage').'"')->toContain('href="'.public_url('b/livepage/qr').'"')->toContain('View live');
    expect($this->actingAs($user)->get('/bio/livepage/edit')->assertOk()->getContent())->toContain("bioPage.is_published ? '".public_url('b')."/' + bioPage.slug : null");
})->group('bio');
