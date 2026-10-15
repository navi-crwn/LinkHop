<?php

use App\Models\BioPage;
use App\Models\Link;
use App\Models\User;
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
