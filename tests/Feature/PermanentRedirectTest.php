<?php

use App\Jobs\LogLinkClick;
use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

// A 301 (or a 308) is stored by the browser, so the visitor never asks again: a warning, a password, a routing
// rule, a limit, an expiry or a new destination added after the first visit would never reach them. A link that
// carries any of those never answers with a permanent redirect, and when it does redirect it answers 302 and is
// not cached. A plain link keeps the type its owner chose.

function permanentLink(array $attributes = []): Link
{
    $link = Link::create([
        'user_id' => User::factory()->create()->id,
        'slug' => 'perm'.fake()->unique()->numberBetween(1000, 9999),
        'target_url' => 'https://example.com/dest',
        'status' => Link::STATUS_ACTIVE,
        'redirect_type' => '301',
    ]);
    if ($attributes) {
        $link->forceFill($attributes)->save();
    }

    return $link->fresh();
}

it('keeps a permanent redirect when the link has nothing that can change', function () {
    Queue::fake([LogLinkClick::class]);
    $link = permanentLink();

    $this->get('/l/'.$link->slug)->assertStatus(301)->assertRedirect('https://example.com/dest');
});

it('keeps a 308 and a 307 as they are when nothing can change', function (string $type, int $expected) {
    Queue::fake([LogLinkClick::class]);
    $link = permanentLink(['redirect_type' => $type]);

    $this->get('/l/'.$link->slug)->assertStatus($expected)->assertRedirect('https://example.com/dest');
})->with([
    'permanent, keeps method' => ['308', 308],
    'temporary, keeps method' => ['307', 307],
]);

it('answers 302 and no-store once a content warning is passed', function () {
    Queue::fake([LogLinkClick::class]);
    $link = permanentLink(['content_warning' => 'sensitive']);

    $this->withSession(['link_gate_passed_'.$link->id => true])
        ->get('/l/'.$link->slug)
        ->assertStatus(302)
        ->assertRedirect('https://example.com/dest')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('answers 302 and no-store once a password is passed', function () {
    Queue::fake([LogLinkClick::class]);
    $link = permanentLink(['password_hash' => bcrypt('secret123')]);

    // The password page itself is never a redirect, so nothing permanent can be stored from it.
    $this->get('/l/'.$link->slug)->assertOk()->assertSee('password');

    $this->withSession(['link_password_'.$link->id => hash('sha256', $link->password_hash)])
        ->get('/l/'.$link->slug)
        ->assertStatus(302)
        ->assertRedirect('https://example.com/dest')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('never answers a permanent redirect for a link that can still change', function (array $attributes) {
    Queue::fake([LogLinkClick::class]);
    $link = permanentLink($attributes);

    $response = $this->get('/l/'.$link->slug);

    expect($response->status())->not->toBeIn([301, 308], 'a stored permanent redirect would hide later changes');
    if ($response->status() === 302) {
        expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
    }
})->with([
    'click limit' => [['max_clicks' => 10]],
    'start time' => [['starts_at' => now()->addHour()]],
    'expiry' => [['expires_at' => now()->addDay()]],
    'fallback address' => [['fallback_url' => 'https://example.com/fallback']],
    'routing rules' => [['routing_rules' => [['country' => 'ID', 'url' => 'https://example.com/id']]]],
    'A/B split' => [['ab_variants' => [['url' => 'https://example.com/a', 'weight' => 50]]]],
    'app links' => [['app_links' => ['mode' => 'auto']]],
    'preview page' => [['enable_preview' => true]],
]);

it('downgrades a 308 as well when the link carries a warning', function () {
    Queue::fake([LogLinkClick::class]);
    $link = permanentLink(['redirect_type' => '308', 'content_warning' => 'adult']);

    $this->withSession(['link_gate_passed_'.$link->id => true])
        ->get('/l/'.$link->slug)
        ->assertStatus(302);
});
