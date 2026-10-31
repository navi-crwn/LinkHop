<?php

use App\Models\Link;
use App\Models\User;
use Hop\Auth\Models\ApiToken;

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
