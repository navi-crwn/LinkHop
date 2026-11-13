<?php

use App\Models\User;
use Hop\Ui\Locales;

/*
| The visual rework (train 5): the create dialog is a slim core with option chips, every chip opens its own
| small sheet, the top bar action is "Create" with a preset menu, and analytics is one toolbar, one KPI card,
| the chart and one card with tabs. These tests pin the shape so a later change does not quietly undo it.
*/

test('the top bar offers Create with presets for a link, a QR code and a deep link', function () {
    $user = User::factory()->create();
    $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
    // the main button still opens the dialog directly (the e2e suite clicks .create-link-trigger)
    expect($html)->toContain('create-link-trigger')->toContain('aria-label="Create"')
        ->toContain('aria-label="Create options"')
        ->toContain("data-args='[\"link\"]'")->toContain("data-args='[\"qr\"]'")->toContain("data-args='[\"deeplink\"]'")
        ->toContain('create=qr')->toContain('create=deeplink');
});

test('the create dialog keeps a slim core and renders every option sheet in the DOM', function () {
    $user = User::factory()->create();
    $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
    // one chip per option group ...
    foreach (['utm', 'qr', 'og', 'schedule', 'protect', 'app', 'routing', 'wa'] as $chip) {
        expect($html)->toContain('data-chip="'.$chip.'"')->toContain('data-sheet="'.$chip.'"');
    }
    // ... and no twelve-groups-in-one block any more
    expect($html)->not->toContain('Advanced options')->not->toContain('showAdvanced');
    // the sheets are real dialogs on their own layer
    expect($html)->toContain('role="dialog"')->toContain('aria-modal="true"');
    // the preview column still sticks while the left column scrolls (the train-4 fix)
    expect($html)->toContain('lg:sticky lg:top-0');
    // the presets open the matching sheet, and ?create=1|qr|deeplink reaches the same dialog
    expect($html)->toContain('window.openLinkPanel = (preset)')
        ->toContain("openSheet('qr')")->toContain("openSheet('app')")
        ->toContain("URLSearchParams(window.location.search).get('create')");
    // the helpers the e2e suite and other tests select are still there
    expect($html)->toContain('x-model="formData.target_url"')
        ->toContain('hopSlugChecker(')->toContain('slugCheck.run(formData.slug)')
        ->toContain('?src=qr');
});

test('a page without the dialog sends the presets to the links page', function () {
    $user = User::factory()->create();
    $html = $this->actingAs($user)->get('/analytics')->assertOk()->getContent();
    expect($html)->toContain('data-fallback-href="'.e(route('links.index')).'?create=qr"');
});

test('analytics is one toolbar, one KPI card and one card with tabs', function () {
    $user = User::factory()->create();
    $html = $this->actingAs($user)->get('/analytics')->assertOk()->getContent();
    // the toolbar keeps the train-4 fixes: the form is its own layer above the cards, the open list is solid
    expect($html)->toContain('glass relative z-20')->toContain('glass-popover');
    // one row: segmented range on wide screens, a compact select on a phone, location and device in one popover
    expect($html)->toContain('pick(24)')->toContain('name="hours"')->toContain('>Filter<');
    // one tabbed card for every breakdown, remembered in the URL
    foreach (["setTab('locations')", "setTab('sources')", "setTab('technology')", "setTab('links')", "setTab('hours')", "setTab('networks')"] as $needle) {
        expect($html)->toContain($needle);
    }
    // the header that repeated the top bar is gone, and the four numbers live in one card
    expect($html)->not->toContain('<h1 class="page-title">Analytics</h1>')
        ->and(substr_count($html, 'stat-label'))->toBe(4);
    // the heatmap uses fixed cells, so a phone never scrolls sideways
    expect($html)->toContain('.heat-grid { display: grid; gap: 2px; grid-template-columns: 1.75rem repeat(24, 10px);')
        ->not->toContain('min-w-[560px]');
    // the map is only built when its tab shows it
    expect($html)->toContain('initAnalyticsMap');
    // the rank lists toggle their length with translated words (the button only renders with data, so pin the source)
    expect(file_get_contents(resource_path('views/components/analytics/rank-list.blade.php')))
        ->toContain("__('Show less')")->toContain("__('Show all (:count)')");
});

test('the create dialog and analytics strings exist in all nine languages', function () {
    // One-word labels may legitimately stay the same word in several languages ('Filter' in German, 'Link' in
    // Indonesian, 'QR code' in French); prose must always be translated, which DedicatedPagesLanguageTest
    // enforces for the whole catalog. These are the strings the rework added or moved.
    $labels = ['Create', 'Link', 'QR code', 'Filter', 'Protection', 'Done', 'Custom', 'Auto'];
    $prose = ['Create options', 'Deep link', 'Link options', 'Schedule & limits', 'Social preview', 'Networks',
        'Show map', 'Hide map', 'Show all (:count)', 'Show less', ':count set', ':count rules',
        ':count tag(s) selected', 'Create a link', 'Breakdown', 'Tap a country', 'No clicks yet',
        'Enter a destination URL.', 'Could not create link (:status).'];
    foreach (Locales::codes() as $lang) {
        $file = json_decode(file_get_contents(base_path("lang/$lang.json")), true);
        foreach ([...$labels, ...$prose] as $key) {
            expect($file)->toHaveKey($key);
        }
        if ($lang !== 'en') {
            foreach ($prose as $key) {
                expect($file[$key])->not->toBe($key);
            }
        }
    }
});
