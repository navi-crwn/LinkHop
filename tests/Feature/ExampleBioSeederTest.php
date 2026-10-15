<?php

use App\Models\BioPage;
use App\Models\Link;
use App\Models\User;
use App\Support\ReservedSlugs;
use Database\Seeders\ExampleBioSeeder;
use Illuminate\Support\Facades\Storage;

test('example bio seeder builds the showcase page and short links', function () {
    Storage::fake('public');
    $this->seed(ExampleBioSeeder::class);
    $this->seed(ExampleBioSeeder::class); // running again resets instead of duplicating

    $page = BioPage::where('slug', 'hel')->firstOrFail();
    expect($page->links()->count())->toBe(9);
    expect(Link::whereIn('slug', array_keys(ExampleBioSeeder::SHORT_LINKS))->count())->toBe(count(ExampleBioSeeder::SHORT_LINKS));
    $this->get('/b/hel')->assertOk()->assertSee('Source code on GitHub')->assertSee('Do I need an account?');
});

test('example bio seeder leaves slugs owned by other accounts alone', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    BioPage::create(['user_id' => $owner->id, 'slug' => 'hel', 'title' => 'Real page', 'is_published' => true]);
    Link::create(['user_id' => $owner->id, 'slug' => 'github', 'target_url' => 'https://example.com', 'status' => 'active']);

    $this->seed(ExampleBioSeeder::class);

    expect(BioPage::where('slug', 'hel')->first()->title)->toBe('Real page');
    expect(Link::where('slug', 'github')->first()->target_url)->toBe('https://example.com');
});

test('example bio seeder can hand the example to an existing account', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    BioPage::create(['user_id' => $owner->id, 'slug' => 'hel', 'title' => 'Old page', 'is_published' => true]);
    putenv('EXAMPLE_BIO_OWNER=owner@example.com');
    try {
        $this->seed(ExampleBioSeeder::class);
    } finally {
        putenv('EXAMPLE_BIO_OWNER');
    }

    $page = BioPage::where('slug', 'hel')->first();
    expect($page->user_id)->toBe($owner->id)->and($page->title)->toBe('HEL.ink');
    expect(Link::where('slug', 'github')->first()->user_id)->toBe($owner->id);
});

test('every example short link opens its target instead of an app page', function () {
    Storage::fake('public');
    $this->seed(ExampleBioSeeder::class);

    foreach (ExampleBioSeeder::SHORT_LINKS as $slug => $target) {
        expect(ReservedSlugs::isReserved($slug))->toBeFalse("/{$slug} is reserved or an app route");
        $this->get("/{$slug}")->assertRedirect($target);
    }
    expect(ExampleBioSeeder::SHORT_LINKS['documentation'] ?? null)->toBe(ExampleBioSeeder::SITE.'#api');
});

test('example bio seeder removes its old api-docs link but not anyone else\'s', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    Link::create(['user_id' => $owner->id, 'slug' => 'api-docs', 'target_url' => ExampleBioSeeder::SITE.'#api', 'status' => 'active']);
    putenv('EXAMPLE_BIO_OWNER=owner@example.com');
    try {
        $this->seed(ExampleBioSeeder::class);
    } finally {
        putenv('EXAMPLE_BIO_OWNER');
    }
    expect(Link::where('slug', 'api-docs')->exists())->toBeFalse();

    // Someone else's, or the owner's own link pointing elsewhere, stays.
    $other = User::factory()->create();
    Link::create(['user_id' => $other->id, 'slug' => 'api-docs', 'target_url' => ExampleBioSeeder::SITE.'#api', 'status' => 'active']);
    $this->seed(ExampleBioSeeder::class);
    expect(Link::where('slug', 'api-docs')->value('user_id'))->toBe($other->id);
});
