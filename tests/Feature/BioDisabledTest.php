<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * LinkHop with BIO_ENABLED=false: no bio routes, menus or quota, and the
 * commands and account pages work without the bio tables. The env is set before
 * the app boots for this file, and the bio tables are dropped so that a missing
 * table would surface here (RefreshDatabase keeps the in-memory database between tests).
 */
beforeAll(function () {
    $_ENV['BIO_ENABLED'] = $_SERVER['BIO_ENABLED'] = 'false';
});
afterAll(function () {
    unset($_ENV['BIO_ENABLED'], $_SERVER['BIO_ENABLED']);
});
beforeEach(function () {
    expect(bio_enabled())->toBeFalse();
    Schema::disableForeignKeyConstraints();
    foreach (['bio_clicks', 'bio_links', 'bio_pages'] as $table) {
        Schema::dropIfExists($table);
    }
    Schema::enableForeignKeyConstraints();
    // So the next file's migrate recreates them (their migrations live in packages/bio).
    DB::table('migrations')->where('migration', 'like', '%bio%')->delete();
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
    expect(bio_enabled())->toBeFalse();
    $this->artisan('db:seed', ['--class' => 'ExampleBioSeeder'])->assertSuccessful();
    expect(Link::where('slug', 'github')->exists())->toBeTrue();
});
