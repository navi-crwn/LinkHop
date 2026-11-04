<?php

use App\Models\Link;
use App\Models\User;
use Hop\Bio\Models\BioPage;

/*
| Markup the audit's browser run flagged: one <main> per page, no repeated ids, code blocks a keyboard can scroll,
| single-column grids that may shrink (320 px), and an editor header that wraps. The layouts themselves were checked
| in a browser at 320 and 390 px; these tests keep the markup that makes them work.
*/

it('has one main landmark on every info page', function (string $path) {
    $html = $this->get($path)->assertOk()->getContent();

    expect(preg_match_all('~<main[\s>]~', $html))->toBe(1)
        ->and(preg_match_all('~id="main"~', $html))->toBeLessThanOrEqual(1);
})->with(['/faq', '/docs', '/terms', '/privacy', '/contact', '/about', '/help', '/status', '/changelog', '/features']);

it('does not repeat the theme button id in the bio editor', function () {
    $user = User::factory()->create();
    $page = BioPage::create(['user_id' => $user->id, 'slug' => 'a11ybio', 'title' => 'A11y', 'is_published' => true]);

    $html = $this->actingAs($user)->get(route('bio.edit', $page))->assertOk()->getContent();

    expect(substr_count($html, 'id="theme-toggle-button"'))->toBe(1)
        // the header may wrap below 400 px, so the Save button is never pushed out of view; the tab strip fades at its edges
        ->and($html)->toContain('class="flex flex-wrap items-center justify-between gap-3"')
        ->and($html)->toContain('mask-image: linear-gradient(to right, transparent, #000 16px');
})->group('bio');

it('lets a keyboard scroll the code blocks', function () {
    $this->actingAs(User::factory()->create());
    foreach (['/docs', '/api-docs'] as $path) {
        $html = $this->get($path)->assertOk()->getContent();
        $pres = preg_match_all('~<pre\b[^>]*>~', $html, $m);
        expect($pres)->toBeGreaterThan(0);
        foreach ($m[0] as $tag) {
            expect($tag)->toContain('tabindex="0"')->toContain('role="region"')->toContain('aria-label=');
        }
    }
});

it('gives the link analytics grids a single column that may shrink', function () {
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'narrowone', 'target_url' => 'https://example.com/'.str_repeat('long-segment-', 12), 'status' => 'active']);

    $html = $this->actingAs($user)->get(route('links.show', $link))->assertOk()->getContent();

    // a grid with only a "lg:" column count has an implicit column as wide as its widest child
    expect($html)->not->toMatch('~class="grid (?:items-start )?gap-3 lg:grid-cols-\d"~')->toContain('grid grid-cols-1 gap-3 lg:grid-cols-4');
});

it('gives the bio analytics grids a single column that may shrink', function () {
    $user = User::factory()->create();
    $page = BioPage::create(['user_id' => $user->id, 'slug' => 'narrowbio', 'title' => 'Narrow', 'is_published' => true]);

    $html = $this->actingAs($user)->get(route('bio.analytics', $page))->assertOk()->getContent();

    expect($html)->not->toMatch('~class="grid (?:items-start )?gap-3 lg:grid-cols-\d"~')->toContain('grid grid-cols-1 gap-3 lg:grid-cols-3');
})->group('bio');
