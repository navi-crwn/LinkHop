<?php

use App\Jobs\LogLinkClick;
use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

dataset('hub page names', require __DIR__.'/../config-hub-pages.php');

test('missing cached hub redirects recover without caching or forwarding query data', function (string $slug) {
    $response = $this->get('/l/'.$slug.'?next=https://example.org/&r=other');
    $response->assertStatus(302)->assertRedirect('https://hel.ink/'.$slug.'?r=l');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
})->with('hub page names');

test('existing links take precedence over every recovery name', function (string $slug) {
    Queue::fake([LogLinkClick::class]);
    Link::create(['user_id' => User::factory()->create()->id, 'slug' => $slug,
        'target_url' => 'https://example.org/real-link', 'status' => Link::STATUS_ACTIVE]);
    $this->get('/l/'.$slug)->assertRedirect('https://example.org/real-link');
})->with('hub page names');

test('recovery does not expose inactive or password protected real links', function () {
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'apps', 'target_url' => 'https://example.org/', 'status' => 'disabled']);
    Link::create(['user_id' => $user->id, 'slug' => 'security', 'target_url' => 'https://example.org/',
        'status' => Link::STATUS_ACTIVE, 'password_hash' => bcrypt('test-password')]);
    $this->get('/l/apps')->assertNotFound();
    $this->get('/l/security')->assertOk()->assertViewIs('link-password');
    $this->get('/l/not-a-hub-page')->assertNotFound();
    $this->post('/l/about')->assertNotFound();
});
