<?php

use App\Models\Link;
use Hop\Safety\Models\DomainBlacklist;

test('link checker shows the destination without counting a click', function () {
    Link::create(['slug' => 'abc123', 'target_url' => 'https://example.com/landing', 'status' => 'active']);

    $this->get('/check?url='.urlencode(config('app.url').'/abc123'))
        ->assertOk()
        ->assertSee('https://example.com/landing')
        ->assertSee('No known issues');
    expect(Link::where('slug', 'abc123')->value('clicks'))->toBe(0);
});

test('link checker hides password protected destinations', function () {
    Link::create(['slug' => 'secret1', 'target_url' => 'https://example.com/private', 'status' => 'active', 'password_hash' => bcrypt('x')]);

    $this->get('/check?url=secret1')->assertOk()->assertSee('password protected')->assertDontSee('example.com/private');
});

test('link checker warns about blocked destinations and rejects other hosts', function () {
    DomainBlacklist::create(['domain' => 'evil.test', 'match_type' => 'exact', 'category' => 'malware']);
    Link::create(['slug' => 'bad123', 'target_url' => 'https://evil.test/x', 'status' => 'active']);

    $this->get('/check?url=bad123')->assertOk()->assertSee('Unsafe destination')->assertDontSee('Open destination');
    $this->get('/check?url='.urlencode('https://bit.ly/xyz'))->assertOk()->assertSee("isn't a", false);
    $this->get('/check?url=nope999')->assertOk()->assertSee('No link found');
});
