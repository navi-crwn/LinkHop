<?php

use App\Models\BioPage;
use App\Models\User;

test('members need a bio slug of at least five letters or numbers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/bio/check-slug?slug=abcd')->assertJson(['available' => false]);
    $this->actingAs($user)->getJson('/bio/check-slug?slug=abcde')->assertJson(['available' => true]);
    $this->actingAs($user)->getJson('/bio/check-slug?slug=ab-cde')->assertJson(['available' => false]);

    $this->actingAs($user)->post('/bio', ['slug' => 'abcd', 'title' => 'Mine'])->assertSessionHasErrors('slug');
    $this->actingAs($user)->post('/bio', ['slug' => 'abcde', 'title' => 'Mine'])->assertSessionHasNoErrors();
});

test('an existing short bio slug stays valid and hel.ink admins may use short slugs', function () {
    $user = User::factory()->create();
    $page = BioPage::create(['user_id' => $user->id, 'slug' => 'old', 'title' => 'P', 'theme' => 'default', 'is_published' => true]);
    $this->actingAs($user)->putJson('/bio/old', ['slug' => 'old', 'title' => 'Renamed'])->assertOk();
    $this->actingAs($user)->putJson('/bio/old', ['slug' => 'new'])->assertJsonValidationErrors('slug');

    $admin = User::factory()->create(['email' => config('auth.superadmin_email', 'admin@hel.ink')]);
    $this->actingAs($admin)->getJson('/bio/check-slug?slug=hel')->assertJson(['available' => true]);
});
