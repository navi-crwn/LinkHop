<?php

use App\Models\User;
use Hop\Bio\Models\BioPage;
use Hop\Bio\Support\UploadStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('bio storage pauses uploads while text edits work and recovers', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $page = BioPage::create(['user_id' => $user->id, 'slug' => 'storage-test', 'title' => 'Before']);
    $this->actingAs($user);
    $this->partialMock(UploadStorage::class, fn ($mock) => $mock->shouldReceive('available')->andReturn(false));
    $this->getJson('/bio/upload-status')->assertOk()->assertJsonPath('available', false);
    foreach (['image' => 'image', 'avatar' => 'avatar', 'thumbnail' => 'thumbnail', 'icon' => 'icon', 'qr-logo' => 'logo'] as $route => $field) {
        $this->postJson('/bio/storage-test/upload-'.$route, [$field => UploadedFile::fake()->image('test.png')])
            ->assertStatus(507)->assertJsonPath('code', 'storage_unavailable');
    }
    $this->putJson('/bio/storage-test', ['title' => 'Text works'])->assertOk();
    expect($page->fresh()->title)->toBe('Text works');
    $this->partialMock(UploadStorage::class, fn ($mock) => $mock->shouldReceive('available')->andReturn(true));
    $this->getJson('/bio/upload-status')->assertOk()->assertJsonPath('available', true);
    $this->postJson('/bio/storage-test/upload-avatar', ['avatar' => UploadedFile::fake()->image('test.png')])->assertOk();
})->group('bio');

test('bio views preserve revisions and avatar revisions permit the following save', function () {
    Storage::fake('public');
    $this->travelTo(now()->startOfSecond());
    $user = User::factory()->create();
    $page = BioPage::create(['user_id' => $user->id, 'slug' => 'revision-test', 'title' => 'Before', 'is_published' => true]);
    $base = $page->updated_at->toJSON();
    $this->travel(5)->seconds();
    $this->get('/b/revision-test')->assertOk();
    expect($page->fresh()->updated_at->toJSON())->toBe($base);
    $this->actingAs($user)->putJson('/bio/revision-test', ['title' => 'After view', 'base_updated_at' => $base])->assertOk();
    $this->travel(5)->seconds();
    $upload = $this->postJson('/bio/revision-test/upload-avatar', ['avatar' => UploadedFile::fake()->image('avatar.png')])->assertOk();
    $this->putJson('/bio/revision-test', ['title' => 'After avatar', 'base_updated_at' => $upload->json('page_updated_at')])->assertOk();
    $this->putJson('/bio/revision-test', ['title' => 'Stale', 'base_updated_at' => $base])->assertConflict();
})->group('bio');
