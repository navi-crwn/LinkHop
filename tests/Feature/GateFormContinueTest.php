<?php

use App\Jobs\LogLinkClick;
use App\Models\Folder;
use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

// A gate form (password, content warning, unverified destination) used to answer with a redirect chain that ended on
// another site. Browsers check CSP form-action against every redirect after a form post, so the visitor stayed on the
// gate, every attempt was logged as a click, and a password page kept showing its last "Incorrect password." even when
// the right one was sent. Now an accepted form gets a page that opens the link with a fresh GET.

function gateLink(array $attributes = []): Link
{
    return Link::create([
        'user_id' => User::factory()->create()->id,
        'slug' => 'gate'.fake()->unique()->numberBetween(100, 999),
        'target_url' => 'https://example.com/dest',
        'status' => Link::STATUS_ACTIVE,
        ...$attributes,
    ]);
}

function assertOpensAgain($response, Link $link): void
{
    $response->assertOk()->assertSee('Continue to example.com');
    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
    expect((string) $response->headers->get('Refresh'))->toStartWith('0;url=')->toEndWith('/l/'.$link->slug);
    expect($response->headers->get('Location'))->toBeNull();
}

it('answers an accepted content warning with a page that opens the link again, not a redirect', function () {
    Queue::fake([LogLinkClick::class]);
    $link = gateLink(['content_warning' => 'sensitive']);

    assertOpensAgain($this->post('/l/'.$link->slug, ['gate_confirm' => 'yes']), $link);
    Queue::assertNothingPushed();

    $this->get('/l/'.$link->slug)->assertRedirect('https://example.com/dest');
    Queue::assertPushed(LogLinkClick::class, 1);
});

it('keeps the remember cookie on the page that opens the link again', function () {
    $link = gateLink(['content_warning' => 'adult']);

    $this->post('/l/'.$link->slug, ['gate_confirm' => 'yes', 'remember' => '1'])
        ->assertOk()->assertCookie('hop_gate_link_'.$link->slug, '1');
});

it('opens a password link after the right password, with no click for either post', function () {
    Queue::fake([LogLinkClick::class]);
    $link = gateLink(['password_hash' => Hash::make('secret123')]);

    $this->get('/l/'.$link->slug)->assertOk()->assertSee('name="password"', false);
    $this->post('/l/'.$link->slug, ['password' => 'wrong-one'])->assertRedirect()->assertSessionHasErrors('password');
    Queue::assertNothingPushed();

    assertOpensAgain($this->post('/l/'.$link->slug, ['password' => 'secret123']), $link);
    Queue::assertNothingPushed();

    $this->get('/l/'.$link->slug)->assertRedirect('https://example.com/dest');
    Queue::assertPushed(LogLinkClick::class, 1);
});

it('does not take a click from a limited link for the password post', function () {
    $link = gateLink(['password_hash' => Hash::make('secret123'), 'max_clicks' => 1]);

    $this->post('/l/'.$link->slug, ['password' => 'secret123'])->assertOk();
    expect($link->fresh()->clicks)->toBe(0);

    $this->get('/l/'.$link->slug)->assertRedirect('https://example.com/dest');
    expect($link->fresh()->clicks)->toBe(1);
});

it('accepts the password of a link made in the dashboard modal (JSON create)', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->postJson(route('links.store'), [
        'target_url' => 'https://example.com/dest', 'slug' => 'modalpw', 'status' => 'active', 'folder_id' => null,
        'tags' => [], 'routing_rules' => [], 'ab_variants' => [], 'password' => 'secret123', 'redirect_type' => '302',
        'content_warning' => 'none', 'generate_qr' => false,
    ])->assertOk()->assertJsonPath('success', true);

    $link = Link::where('slug', 'modalpw')->sole();
    assertOpensAgain($this->post('/l/modalpw', ['password' => 'secret123']), $link);
    $this->get('/l/modalpw')->assertRedirect('https://example.com/dest');
});

it('goes from the password to the content warning, then opens the link again', function () {
    $link = gateLink(['password_hash' => Hash::make('secret123'), 'content_warning' => 'adult']);

    $this->post('/l/'.$link->slug, ['password' => 'secret123'])->assertOk()->assertSee('Age Verification Required');
    assertOpensAgain($this->post('/l/'.$link->slug, ['gate_confirm' => 'yes']), $link);
    $this->get('/l/'.$link->slug)->assertRedirect('https://example.com/dest');
});

it('offers no folder on the create form and keeps a link unfiled when asked', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $first = Folder::create(['name' => 'Alpha', 'user_id' => $user->id]);
    $default = Folder::create(['name' => 'Inbox', 'user_id' => $user->id, 'is_default' => true]);

    $this->actingAs($user)->get(route('links.create'))->assertOk()
        ->assertSee('No folder')
        ->assertSee('<option value="'.$default->id.'" selected', false);

    $this->post(route('links.store'), ['target_url' => 'https://example.com/a', 'slug' => 'unfiled1', 'status' => 'active', 'folder_id' => ''])
        ->assertSessionHasNoErrors();
    expect(Link::where('slug', 'unfiled1')->value('folder_id'))->toBeNull();

    $this->post(route('links.store'), ['target_url' => 'https://example.com/b', 'slug' => 'filed1', 'status' => 'active', 'folder_id' => $first->id])
        ->assertSessionHasNoErrors();
    expect(Link::where('slug', 'filed1')->value('folder_id'))->toBe($first->id);

    // A client that sends no folder at all still gets the default folder.
    $this->post(route('links.store'), ['target_url' => 'https://example.com/c', 'slug' => 'nokey1', 'status' => 'active'])
        ->assertSessionHasNoErrors();
    expect(Link::where('slug', 'nokey1')->value('folder_id'))->toBe($default->id);
});

it('selects no folder on the create form when there is no default folder', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Folder::create(['name' => 'Alpha', 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('links.create'))->assertOk()
        ->assertSee('<option value="" selected', false);
});
