<?php

use App\Models\User;
use Hop\Bio\Models\BioLink;
use Hop\Bio\Models\BioPage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function uploadsPage(string $slug, array $attrs = []): BioPage
{
    return BioPage::create(array_merge([
        'user_id' => User::factory()->create()->id,
        'slug' => $slug,
        'title' => 'Page',
        'theme' => 'default',
        'is_published' => true,
    ], $attrs));
}

test('a user cannot delete another user\'s avatar by pointing avatar_url at it', function () {
    Storage::fake('public');
    $victim = uploadsPage('victimpage');
    $this->actingAs($victim->user)
        ->post('/bio/victimpage/upload-avatar', ['avatar' => UploadedFile::fake()->image('me.jpg')])->assertOk();
    $victimPath = $victim->fresh()->avatar_url;
    Storage::disk('public')->assertExists($victimPath);

    $attacker = uploadsPage('attackerpage');
    $this->actingAs($attacker->user)->putJson('/bio/attackerpage', ['avatar_url' => $victimPath]);
    $this->actingAs($attacker->user)
        ->post('/bio/attackerpage/upload-avatar', ['avatar' => UploadedFile::fake()->image('x.jpg')])->assertOk();
    $this->actingAs($attacker->user)->delete('/bio/attackerpage');

    Storage::disk('public')->assertExists($victimPath);
});

test('qr settings cannot be used to delete another user\'s file', function () {
    Storage::fake('public');
    Storage::disk('public')->put('bio-images/victim.png', 'x');
    uploadsPage('owner2', ['background_value' => 'url(/storage/bio-images/victim.png)']);

    $attacker = uploadsPage('attacker2');
    $this->actingAs($attacker->user)->putJson('/bio/attacker2', ['qr_settings' => ['image' => '/storage/bio-images/victim.png']]);
    $this->actingAs($attacker->user)->putJson('/bio/attacker2', ['qr_settings' => ['image' => null]]);

    Storage::disk('public')->assertExists('bio-images/victim.png');
});

test('replacing your own avatar removes the old file', function () {
    Storage::fake('public');
    $page = uploadsPage('ownpage');
    $this->actingAs($page->user)->post('/bio/ownpage/upload-avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $old = $page->fresh()->avatar_url;
    $this->actingAs($page->user)->post('/bio/ownpage/upload-avatar', ['avatar' => UploadedFile::fake()->image('b.jpg')]);

    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($page->fresh()->avatar_url);
});

test('a duplicated page keeps the original page\'s avatar when the copy is deleted', function () {
    Storage::fake('public');
    $page = uploadsPage('dupavatar');
    $this->actingAs($page->user)->post('/bio/dupavatar/upload-avatar', ['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $this->actingAs($page->user)->post('/bio/dupavatar/duplicate');
    $copy = BioPage::where('id', '!=', $page->id)->sole();

    $this->actingAs($page->user)->delete("/bio/{$copy->slug}");

    Storage::disk('public')->assertExists($page->fresh()->avatar_url);
});

test('duplicating a long-slug page twice gives two distinct short slugs', function () {
    $page = uploadsPage('abcdefghijklmnopqrst');
    $this->actingAs($page->user)->post('/bio/abcdefghijklmnopqrst/duplicate')->assertRedirect();
    $this->actingAs($page->user)->post('/bio/abcdefghijklmnopqrst/duplicate')->assertRedirect();

    $slugs = BioPage::where('id', '!=', $page->id)->pluck('slug');
    expect($slugs)->toHaveCount(2)->and($slugs->unique())->toHaveCount(2)
        ->and($slugs->every(fn ($s) => strlen($s) <= 20))->toBeTrue();
});

test('block animations and the tiktok pixel are saved', function () {
    $page = uploadsPage('animpage');
    $block = BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'A', 'url' => 'https://example.com', 'is_active' => true, 'order' => 0]);

    $this->actingAs($page->user)->putJson("/bio/animpage/links/{$block->id}", ['entrance_animation' => 'slide-up', 'attention_animation' => 'heartbeat'])->assertOk();
    expect($block->fresh())->entrance_animation->toBe('slide-up')->attention_animation->toBe('heartbeat');
    $this->actingAs($page->user)->putJson("/bio/animpage/links/{$block->id}", ['entrance_animation' => 'x;}body{'])->assertUnprocessable();

    $this->actingAs($page->user)->putJson('/bio/animpage', ['tiktok_pixel_id' => 'C4ABCDEFGHIJKLMNOPQR12'])->assertOk();
    expect($page->fresh()->tiktok_pixel_id)->toBe('C4ABCDEFGHIJKLMNOPQR12');
    $this->actingAs($page->user)->putJson('/bio/animpage', ['tiktok_pixel_id' => "x');alert(1);//"])->assertUnprocessable();
});

test('the public page loads only the chosen font', function () {
    uploadsPage('fontpage', ['font_family' => 'merriweather']);

    $html = $this->get('/b/fontpage')->assertOk()->getContent();
    expect($html)->toContain('css2?family=Merriweather:')
        ->not->toContain('family=Inter:')
        ->not->toContain('@import url(\'https://fonts.googleapis.com');
});

test('the editor preview only accepts messages from its own editor frame', function () {
    $page = uploadsPage('previewpage');

    $html = $this->actingAs($page->user)->get('/bio/previewpage/preview')->assertOk()->getContent();
    expect($html)->toContain('event.origin !== window.location.origin')
        ->not->toContain("postMessage({ type: 'preview-ready' }, '*')")
        ->not->toContain('.replace(/on\\w+\\s*=');
});
