<?php

use App\Mail\ShortLinkMigrationNotice;
use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

test('short links live at PUBLIC_URL/l/{slug} and PUBLIC_URL may differ from APP_URL', function () {
    config(['shortener.public_url' => 'https://hel.ink']);
    $link = Link::create(['slug' => 'promo', 'target_url' => 'https://example.com/p', 'status' => 'active']);

    expect($link->short_url)->toBe('https://hel.ink/l/promo')
        ->and($link->legacy_url)->toBe('https://hel.ink/promo')
        ->and(public_url('b/me'))->toBe('https://hel.ink/b/me')
        ->and(public_host())->toBe('hel.ink')
        ->and(url('/'))->toBe(config('app.url'));
});

test('/l/{slug} opens the link and the retired old form /{slug} answers 404', function () {
    Link::create(['slug' => 'promo1', 'target_url' => 'https://example.com/p', 'status' => 'active']);

    $this->get('/l/promo1')->assertRedirect('https://example.com/p')->assertStatus(302);
    $this->get('/promo1')->assertNotFound();
    $this->get('/l/nothere')->assertNotFound();
    $this->get('/nothere')->assertNotFound();
    $this->get('/l/promo1/extra')->assertNotFound();
});

test('a password-protected link asks for the password at /l/ and the form posts there', function () {
    $link = Link::create(['slug' => 'secret1', 'target_url' => 'https://example.com/s', 'status' => 'active', 'password_hash' => bcrypt('pw')]);

    $this->get('/l/secret1')->assertOk()->assertSee('action="'.url('/l/secret1').'"', false);
    $this->post('/l/secret1', ['password' => 'pw'])->assertRedirect('https://example.com/s');
});

test('the retired old form is a 404 while /l/ keeps working', function () {
    Link::create(['user_id' => User::factory()->create()->id, 'slug' => 'promo2', 'target_url' => 'https://example.com/p', 'status' => 'active']);

    $this->get('/promo2')->assertNotFound();
    $this->get('/l/promo2')->assertRedirect('https://example.com/p');
});

test('the API, the dashboard and the sitemap use the /l/ form', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/api', 'slug' => 'viaapi', 'status' => 'active'])
        ->assertOk()->assertJsonPath('link.short_url', public_url('l/viaapi'));
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('/l/viaapi');
    $this->actingAs($user)->get('/links/create')->assertOk()->assertSee(public_host().'/l/');
});

test('the dashboard announces the move until the legacy date, with the date', function () {
    $user = User::factory()->create();
    config(['shortener.legacy_slug_until' => '2026-11-27']);
    Carbon::setTestNow('2026-11-01');
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee(public_host().'/l/')->assertSee('27 November 2026');
    $this->actingAs($user)->get('/changes/short-links')->assertOk()->assertSee('27 November 2026');
    Carbon::setTestNow();
});

test('the dashboard drops the notice once the legacy date has passed', function () {
    config(['shortener.legacy_slug_until' => '2026-11-27']);
    Carbon::setTestNow('2026-12-01');
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertDontSee('27 November 2026');
    Carbon::setTestNow();
});

test('links:announce-migration mails every link owner their links in the new form', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $nobody = User::factory()->create();
    Link::create(['user_id' => $owner->id, 'slug' => 'mine1', 'target_url' => 'https://example.com/1', 'status' => 'active']);
    Link::create(['user_id' => $owner->id, 'slug' => 'mine2', 'target_url' => 'https://example.com/2', 'status' => 'active']);
    Link::create(['user_id' => $other->id, 'slug' => 'theirs', 'target_url' => 'https://example.com/3', 'status' => 'active']);
    Link::create(['slug' => 'guest1', 'target_url' => 'https://example.com/4', 'status' => 'active']);

    $this->artisan('links:announce-migration')->expectsOutputToContain('2 owners')->assertSuccessful();

    Mail::assertQueued(ShortLinkMigrationNotice::class, 2);
    Mail::assertQueued(ShortLinkMigrationNotice::class, fn ($mail) => $mail->hasTo($owner->email) && $mail->links->count() === 2);
    Mail::assertNotQueued(ShortLinkMigrationNotice::class, fn ($mail) => $mail->hasTo($nobody->email));

    $mail = new ShortLinkMigrationNotice($owner, Link::where('user_id', $owner->id)->get(), false);
    $mail->assertSeeInHtml(public_url('l/mine1'))->assertSeeInHtml(public_url('l/mine2'))->assertSeeInHtml('27 November 2026');
    (new ShortLinkMigrationNotice($owner, Link::where('user_id', $owner->id)->get(), true))->assertSeeInHtml('Reminder');
});
