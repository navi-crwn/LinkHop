<?php

use App\Models\Link;
use App\Models\User;
use App\Services\TurnstileService;
use Hop\Auth\Models\ApiToken;

test('guest shortener preserves content classification', function () {
    $this->mock(TurnstileService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });
    $this->post(route('shorten.store'), [
        'target_url' => 'https://example.com/story',
        'slug' => 'guest-warning',
        'content_warning' => 'sensitive',
        'warning_reason' => 'Story spoilers',
    ])->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseHas('links', [
        'slug' => 'guest-warning',
        'content_warning' => 'sensitive',
        'warning_reason' => 'Story spoilers',
    ]);
});

test('standard link redirects immediately without gate', function () {
    $user = User::factory()->create();
    $link = Link::create([
        'user_id' => $user->id,
        'slug' => 'clean-link',
        'target_url' => 'https://example.com/target',
        'status' => Link::STATUS_ACTIVE,
        'content_warning' => 'none',
    ]);

    $response = $this->get('/l/'.$link->slug);
    $response->assertStatus(302);
    $response->assertRedirect('https://example.com/target');
});

test('sensitive link displays content warning gate', function () {
    $user = User::factory()->create();
    $link = Link::create([
        'user_id' => $user->id,
        'slug' => 'sensitive-link',
        'target_url' => 'https://example.com/sensitive',
        'status' => Link::STATUS_ACTIVE,
        'content_warning' => 'sensitive',
        'warning_reason' => 'Contains intense spoiler material.',
    ]);

    $response = $this->get('/l/'.$link->slug);
    $response->assertOk();
    $response->assertSee('Sensitive Content Warning');
    $response->assertSee('Contains intense spoiler material.');
    $response->assertSee('I understand — Continue');
    $response->assertSee('Remember for 24 hours on this device');
});

test('sensitive link gate can be unlocked via form post without js', function () {
    $user = User::factory()->create();
    $link = Link::create([
        'user_id' => $user->id,
        'slug' => 'sensitive-unlock',
        'target_url' => 'https://example.com/destination',
        'status' => Link::STATUS_ACTIVE,
        'content_warning' => 'sensitive',
    ]);

    $postResponse = $this->post('/l/'.$link->slug, [
        'gate_confirm' => 'yes',
    ]);

    $postResponse->assertStatus(303);
    $postResponse->assertSessionHas('link_gate_passed_'.$link->id, true);

    // Subsequent GET request with session redirects to destination
    $redirectResponse = $this->get('/l/'.$link->slug);
    $redirectResponse->assertStatus(302);
    $redirectResponse->assertRedirect('https://example.com/destination');
});

test('adult link gate and remember cookie', function () {
    $user = User::factory()->create();
    $link = Link::create([
        'user_id' => $user->id,
        'slug' => 'adult-link',
        'target_url' => 'https://example.com/mature',
        'status' => Link::STATUS_ACTIVE,
        'content_warning' => 'adult',
    ]);

    // First visit shows 18+ gate
    $response = $this->get('/l/'.$link->slug);
    $response->assertOk();
    $response->assertSee('Age Verification Required');
    $response->assertSee('18+ Age Verification Required');

    // Confirm with remember=1
    $postResponse = $this->post('/l/'.$link->slug, [
        'gate_confirm' => 'yes',
        'remember' => '1',
    ]);

    $postResponse->assertStatus(303);
    $postResponse->assertCookie('hop_gate_link_'.$link->slug, '1');

    // Clear session, make request with cookie only
    $this->flushSession();
    $cookieResponse = $this->withCookie('hop_gate_link_'.$link->slug, '1')
        ->get('/l/'.$link->slug);

    $cookieResponse->assertStatus(302);
    $cookieResponse->assertRedirect('https://example.com/mature');
});

test('check page displays warning badge and creator note', function () {
    $user = User::factory()->create();
    $link = Link::create([
        'user_id' => $user->id,
        'slug' => 'check-warning',
        'target_url' => 'https://example.com/safe-content',
        'status' => Link::STATUS_ACTIVE,
        'content_warning' => 'adult',
        'warning_reason' => 'Mature community guidelines apply.',
    ]);

    $response = $this->get('/check?url='.urlencode(public_url('l/'.$link->slug)));
    $response->assertOk();
    $response->assertSee('18+ Adult');
    $response->assertSee('Mature community guidelines apply.');
});

test('api shorten accepts and returns content warning', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $token = ApiToken::generate($user->id, 'cli', 100, null, ['link:create'])['plain_token'];

    $response = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/shorten', [
        'url' => 'https://example.com/api-target',
        'content_warning' => 'sensitive',
        'warning_reason' => 'Graphic medical demonstration.',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.content_warning', 'sensitive');
    $response->assertJsonPath('data.warning_reason', 'Graphic medical demonstration.');
});

test('password protected links can complete the content gate without losing the password unlock', function () {
    $link = Link::create([
        'user_id' => User::factory()->create()->id,
        'slug' => 'protected-adult',
        'target_url' => 'https://example.com/protected',
        'status' => Link::STATUS_ACTIVE,
        'content_warning' => 'adult',
        'password_hash' => bcrypt('demo-password'),
    ]);

    $this->post('/l/'.$link->slug, ['password' => 'demo-password'])->assertSee('Age Verification Required');
    $this->post('/l/'.$link->slug, ['gate_confirm' => 'yes'])->assertStatus(303);
    $this->get('/l/'.$link->slug)->assertRedirect($link->target_url);
});

test('sensitive gate makes no age claim and remember cookie is host only', function () {
    config(['session.domain' => '.hel.ink']);
    $link = Link::create([
        'user_id' => User::factory()->create()->id,
        'slug' => 'sensitive-host',
        'target_url' => 'https://example.com/sensitive',
        'status' => Link::STATUS_ACTIVE,
        'content_warning' => 'sensitive',
    ]);

    $this->get('/l/'.$link->slug)->assertDontSee('legal age');
    $response = $this->post('/l/'.$link->slug, ['gate_confirm' => 'yes', 'remember' => '1']);
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'hop_gate_link_'.$link->slug);
    expect($cookie->getDomain())->toBeNull();
    expect($cookie->isHttpOnly())->toBeTrue();
    expect($cookie->getExpiresTime() - time())->toBeGreaterThan(86390)->toBeLessThanOrEqual(86400);
});

test('dashboard create and edit forms expose content classification', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $link = Link::create(['user_id' => $user->id, 'slug' => 'editable-warning', 'target_url' => 'https://example.com', 'status' => Link::STATUS_ACTIVE]);
    $this->actingAs($user)->get(route('links.create'))->assertOk()->assertSee('name="content_warning"', false);
    $this->get(route('links.edit', $link))->assertOk()->assertSee('name="content_warning"', false)->assertSee('name="warning_reason"', false);
    $this->put(route('links.update', $link), [
        'target_url' => $link->target_url, 'status' => 'active', 'slug' => $link->slug,
        'content_warning' => 'sensitive', 'warning_reason' => 'Demo spoilers',
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($link->fresh()->content_warning)->toBe('sensitive');
    expect($link->fresh()->warning_reason)->toBe('Demo spoilers');
});
