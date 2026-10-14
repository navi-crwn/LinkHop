<?php

use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\User;
use App\Support\SafeContent;
use Illuminate\Support\Facades\Hash;

function clickPage(array $attrs = []): BioPage
{
    return BioPage::create(array_merge([
        'user_id' => User::factory()->create()->id,
        'slug' => 'clickpage'.uniqid(),
        'title' => 'Click page',
        'theme' => 'default',
        'is_published' => true,
    ], $attrs));
}

function clickBlock(BioPage $page, array $attrs = []): BioLink
{
    return BioLink::create(array_merge([
        'bio_page_id' => $page->id,
        'type' => 'link',
        'title' => 'Go',
        'url' => 'https://example.com/secret-destination',
        'is_active' => true,
        'order' => 0,
    ], $attrs));
}

test('bio links get an unguessable public id and numeric ids no longer resolve', function () {
    $link = clickBlock(clickPage());

    expect($link->public_id)->toMatch('/^[A-Za-z0-9]{12}$/');
    $this->get("/b/click/{$link->id}")->assertNotFound();
    $this->get("/b/click/{$link->public_id}")->assertRedirect('https://example.com/secret-destination');
});

test('the public page links to clicks by public id only', function () {
    $page = clickPage(['slug' => 'publicids']);
    $link = clickBlock($page);

    $html = $this->get('/b/publicids')->assertOk()->getContent();
    expect($html)->toContain("/b/click/{$link->public_id}")
        ->not->toContain("/b/click/{$link->id}\"");
});

test('click on a password protected page does not reveal the destination until unlocked', function () {
    $page = clickPage(['slug' => 'locked', 'password_enabled' => true, 'password' => Hash::make('letmein')]);
    $link = clickBlock($page);

    $this->get("/b/click/{$link->public_id}")->assertRedirect('/b/locked');
    expect($link->fresh()->click_count)->toBe(0);

    $this->post('/b/locked', ['password' => 'letmein'])->assertRedirect();
    $this->get("/b/click/{$link->public_id}")->assertRedirect('https://example.com/secret-destination');
});

test('click on an adult page requires the age confirmation first', function () {
    $page = clickPage(['slug' => 'adultpage', 'is_adult_content' => true]);
    $link = clickBlock($page);

    $this->get("/b/click/{$link->public_id}")->assertRedirect('/b/adultpage');
    $this->post('/b/adultpage', ['age_confirm' => 'yes'])->assertRedirect();
    $this->get("/b/click/{$link->public_id}")->assertRedirect('https://example.com/secret-destination');
});

test('inactive blocks, unpublished pages and suspended owners return 404', function () {
    $page = clickPage();
    $inactive = clickBlock($page, ['is_active' => false]);
    $this->get("/b/click/{$inactive->public_id}")->assertNotFound();

    $link = clickBlock($page);
    $page->user->update(['status' => User::STATUS_SUSPENDED]);
    $this->get("/b/click/{$link->public_id}")->assertNotFound();

    $page->user->update(['status' => 'active']);
    $page->update(['is_published' => false]);
    $this->get("/b/click/{$link->public_id}")->assertNotFound();
});

test('repeated clicks from one visitor count once and bots are not counted', function () {
    $link = clickBlock(clickPage());

    $this->get("/b/click/{$link->public_id}")->assertRedirect();
    $this->get("/b/click/{$link->public_id}")->assertRedirect();
    expect($link->fresh()->click_count)->toBe(1);

    $this->withHeader('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)')
        ->get("/b/click/{$link->public_id}")->assertRedirect();
    expect($link->fresh()->click_count)->toBe(1);
});

test('click redirects send a restrained referrer policy and are throttled per ip', function () {
    $link = clickBlock(clickPage());

    $this->get("/b/click/{$link->public_id}")
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    foreach (range(1, 59) as $i) {
        $this->get("/b/click/{$link->public_id}");
    }
    $this->get("/b/click/{$link->public_id}")->assertStatus(429);
});

test('unsafe destinations are never redirected to', function () {
    $link = clickBlock(clickPage(), ['url' => 'javascript:alert(1)']);

    $this->get("/b/click/{$link->public_id}")->assertNotFound();
});

test('qr codes are not served for hidden pages or suspended owners', function () {
    $page = clickPage(['slug' => 'qrpage']);
    $page->user->update(['status' => User::STATUS_SUSPENDED]);
    $this->get('/b/qrpage/qr')->assertNotFound();

    $page->user->update(['status' => 'active']);
    $page->update(['is_published' => false]);
    $this->get('/b/qrpage/qr')->assertNotFound();
});

test('backslash and whitespace tricks are not treated as same-site paths', function () {
    foreach (['/\\evil.com', "/\t/evil.com", '/\\/evil.com', ' //evil.com'] as $url) {
        expect(SafeContent::url($url))->toBe('#');
    }
    expect(SafeContent::url('/storage/bio-images/a.png'))->toBe('/storage/bio-images/a.png');

    $page = clickPage(['slug' => 'slashpage']);
    $this->actingAs($page->user)
        ->postJson("/bio/{$page->slug}/links", ['type' => 'link', 'title' => 'Login', 'url' => '/\\phish.example/login'])
        ->assertUnprocessable();

    $link = clickBlock($page, ['url' => '/\\evil.com']);
    $this->get("/b/click/{$link->public_id}")->assertNotFound();
});

test('duplicating a page gives the copied blocks their own public ids', function () {
    $page = clickPage(['slug' => 'dupsource']);
    $original = clickBlock($page);

    $this->actingAs($page->user)->post('/bio/dupsource/duplicate')->assertRedirect();

    $copy = BioLink::where('id', '!=', $original->id)->sole();
    expect($copy->public_id)->toMatch('/^[A-Za-z0-9]{12}$/')->not->toBe($original->public_id);
});
