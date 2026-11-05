<?php

use App\Models\User;
use Hop\Bio\Models\BioPage;
use Hop\Ui\Locales;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * A bio page can have a favicon of its own, as in BioHop: an upload of this install under bio-images/, shown in the
 * browser tab of /b/slug instead of LinkHop's. Never an address on another host.
 */
function faviconPage(string $slug, array $attrs = []): BioPage
{
    return BioPage::create(array_merge([
        'user_id' => User::factory()->create()->id,
        'slug' => $slug,
        'title' => 'Favicon page',
        'theme' => 'default',
        'is_published' => true,
    ], $attrs));
}

test('the public page shows the page favicon, and LinkHop\'s without one', function () {
    $page = faviconPage('favpage');
    $brand = route('brand.favicon');

    expect($this->get('/b/favpage')->assertOk()->getContent())->toContain('rel="icon" href="'.$brand.'"');

    $this->actingAs($page->user)->putJson('/bio/favpage', ['favicon' => 'bio-images/fav.png'])->assertOk();
    $html = $this->get('/b/favpage')->assertOk()->getContent();
    expect($html)->toContain('rel="icon" href="'.Storage::disk('public')->url('bio-images/fav.png').'"')
        ->not->toContain('rel="icon" href="'.$brand.'"');

    $this->actingAs($page->user)->putJson('/bio/favpage', ['favicon' => ''])->assertOk();
    expect($page->fresh()->favicon)->toBeNull()
        ->and($this->get('/b/favpage')->assertOk()->getContent())->toContain('rel="icon" href="'.$brand.'"');
});

test('only an upload of this install is accepted as a favicon', function (string $value) {
    $page = faviconPage('favbad');

    $this->actingAs($page->user)->putJson('/bio/favbad', ['favicon' => $value])->assertStatus(422)->assertJsonValidationErrors('favicon');
    expect($page->fresh()->favicon)->toBeNull();
})->with([
    'another host' => 'https://example.com/fav.png',
    'a path with a dot segment' => 'bio-images/../avatars/x.png',
    'the public prefix' => '/storage/bio-images/fav.png',
    'another folder' => 'avatars/fav.png',
    'a data address' => 'data:image/png;base64,AAAA',
]);

test('an uploaded favicon is stored, replaced and deleted with care for other pages', function () {
    Storage::fake('public');
    $page = faviconPage('favfile');
    $this->actingAs($page->user);

    $first = $this->post('/bio/favfile/upload-image', ['image' => UploadedFile::fake()->image('fav.png', 64, 64)])->assertOk()->json('path');
    expect($first)->toMatch('#^bio-images/[A-Za-z0-9._-]+$#');
    $this->putJson('/bio/favfile', ['favicon' => $first])->assertOk();
    expect($page->fresh()->favicon)->toBe($first);
    Storage::disk('public')->assertExists($first);

    // Replacing the favicon removes the old file, which no page uses any more.
    $second = $this->post('/bio/favfile/upload-image', ['image' => UploadedFile::fake()->image('fav2.png', 64, 64)])->json('path');
    $this->putJson('/bio/favfile', ['favicon' => $second])->assertOk();
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    // Another user's favicon cannot be deleted by pointing at it and then clearing it.
    $victim = faviconPage('favvictim', ['favicon' => $second]);
    $this->putJson('/bio/favfile', ['favicon' => ''])->assertOk();
    Storage::disk('public')->assertExists($second);
    expect($victim->fresh()->favicon)->toBe($second);

    // Deleting the page removes its favicon when no other page uses it.
    $this->putJson('/bio/favfile', ['favicon' => $first = $this->post('/bio/favfile/upload-image', ['image' => UploadedFile::fake()->image('fav3.png', 64, 64)])->json('path')])->assertOk();
    $this->delete('/bio/favfile');
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

test('a duplicated page keeps the favicon and the editor offers the upload in the visitor\'s language', function () {
    $page = faviconPage('favdup', ['favicon' => 'bio-images/fav.png']);
    $this->actingAs($page->user);

    $this->post('/bio/favdup/duplicate')->assertRedirect();
    expect(BioPage::where('slug', 'favdupcopy')->value('favicon'))->toBe('bio-images/fav.png');

    // The page is handed to the editor as JSON, where the slash is escaped.
    $this->get('/bio/favdup/edit')->assertOk()->assertSee('Upload favicon')->assertSee('bio-images\\/fav.png', false);
    $this->get('/bio/favdup/edit?lang=id')->assertOk()->assertSee('Unggah favicon');
});

test('the favicon strings are translated in all nine languages', function () {
    foreach (['Favicon', 'The small icon in the browser tab. A square PNG, 64×64 or larger.', 'Upload favicon', 'Favicon uploaded. Save the page to use it.'] as $key) {
        foreach (Locales::codes() as $lang) {
            $file = json_decode(file_get_contents(base_path("lang/{$lang}.json")), true);
            expect($file)->toHaveKey($key);
            if ($lang !== 'en' && $key !== 'Favicon') {
                expect($file[$key])->not->toBe($key);
            }
        }
    }
});
