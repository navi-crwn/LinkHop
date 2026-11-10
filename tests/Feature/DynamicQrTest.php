<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * The QR code of a short link holds the short address (?src=qr), not the destination. These tests lock the behaviour
 * the dashboard texts promise: a new destination keeps the printed code working, a new slug does not.
 */

function qrLink(User $user, string $slug = 'menuqr'): Link
{
    test()->actingAs($user)->post('/links', ['target_url' => 'https://example.com/lunch', 'slug' => $slug, 'status' => 'active'])->assertRedirect();

    return Link::where('slug', $slug)->firstOrFail();
}

beforeEach(fn () => Storage::fake('public'));

test('the code holds the short address with the QR source tag, never the destination', function () {
    $link = qrLink(User::factory()->create());
    expect($link->qr_url)->toEndWith('/l/menuqr?src=qr')->and($link->qr_url)->not->toContain('example.com');
});

test('changing the destination keeps the code file and its bytes', function () {
    $user = User::factory()->create();
    $link = qrLink($user);
    $path = $link->qr_code_path;
    expect($path)->not->toBeNull();
    $before = Storage::disk('public')->get($path);

    $this->actingAs($user)->put('/links/'.$link->id, ['target_url' => 'https://example.com/dinner', 'status' => 'active', 'slug' => 'menuqr'])->assertRedirect();

    $link->refresh();
    expect($link->target_url)->toBe('https://example.com/dinner')->and($link->qr_code_path)->toBe($path)
        ->and(Storage::disk('public')->get($path))->toBe($before);
});

test('changing the slug makes a new code and the old address stops working', function () {
    $user = User::factory()->create();
    $link = qrLink($user);
    $old = $link->qr_code_path;
    $oldBytes = Storage::disk('public')->get($old);
    $this->get('/l/menuqr')->assertRedirect('https://example.com/lunch');

    $this->actingAs($user)->put('/links/'.$link->id, ['target_url' => 'https://example.com/lunch', 'status' => 'active', 'slug' => 'menuqr2'])->assertRedirect();

    $link->refresh();
    expect($link->qr_code_path)->not->toBe($old)->and($link->qr_code_path)->toBe('qrcodes/menuqr2.png')
        ->and(Storage::disk('public')->get($link->qr_code_path))->not->toBe($oldBytes);
    $this->get('/l/menuqr')->assertNotFound();
});

test('the edit and show pages say what changing the destination and the slug does to the code', function () {
    $user = User::factory()->create();
    $link = qrLink($user);
    $this->actingAs($user)->get('/links/'.$link->id.'/edit')
        ->assertOk()
        ->assertSee('The QR code holds the short address. Change the destination any time: the printed code keeps working.')
        ->assertSee('Changing the slug changes the QR code. The old printed code will stop working.');
    $this->actingAs($user)->get('/links/'.$link->id)->assertOk()->assertSee('printed code keeps working');
});
