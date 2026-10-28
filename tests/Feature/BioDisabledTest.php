<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/*
 * LinkHop with BIO_ENABLED=false: no bio routes, menus or quota, and the
 * commands and account pages work without the bio tables. The package reads
 * the flag when the app boots, so these tests only run in a process started
 * with BIO_ENABLED=false (the second test step in CI), where the bio
 * migrations are never loaded and the tables never exist.
 */
beforeEach(function () {
    if (bio_enabled()) {
        $this->markTestSkipped('Run with BIO_ENABLED=false (php artisan test --exclude-group=bio).');
    }
    foreach (['bio_clicks', 'bio_links', 'bio_pages'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse("{$table} should not exist with bio off");
    }
});

test('bio routes are gone and the dashboard shows no bio menu or quota', function () {
    $user = User::factory()->create();
    $this->get('/b/anyone')->assertNotFound();
    $this->actingAs($user)->get('/bio')->assertNotFound();
    $this->actingAs($user)->get('/bio/create')->assertNotFound();
    $this->actingAs($user)->postJson('/api/bio/shorten', ['url' => 'https://example.com'])->assertNotFound();
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('Link in Bio')->assertDontSee('Bio pages');
    expect(app('router')->has('bio.index'))->toBeFalse();
});

test('short links, the sitemap and the link checker work without bio tables', function () {
    Link::create(['slug' => 'nobio1', 'target_url' => 'https://example.com/n', 'status' => 'active']);
    $this->get('/l/nobio1')->assertRedirect('https://example.com/n');
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('/b/');
    $this->get('/check?url=b/someone')->assertOk();
    $this->get('/check?url=nobio1')->assertOk()->assertSee('example.com/n');
});

test('abuse:scan, privacy:prune and the account export and deletion skip bio', function () {
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'nobio2', 'target_url' => 'https://example.com/n', 'status' => 'active']);

    $this->artisan('abuse:scan', ['--full' => true, '--dry-run' => true])->assertSuccessful();
    $this->artisan('privacy:prune')->assertSuccessful();
    $this->actingAs($user)->post('/settings/export-account')->assertOk();
    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect(route('goodbye'));
    expect(User::find($user->id))->toBeNull();
});

test('the example seeder still seeds the short links', function () {
    $this->artisan('db:seed', ['--class' => 'ExampleBioSeeder'])->assertSuccessful();
    expect(Link::where('slug', 'github')->exists())->toBeTrue();
});
