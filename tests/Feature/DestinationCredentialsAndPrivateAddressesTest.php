<?php

use App\Models\Link;
use App\Models\User;
use App\Support\LinkImport;
use Hop\Auth\Models\ApiToken;
use Hop\Bio\Models\BioLink;
use Hop\Bio\Models\BioPage;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

/*
| A short link with a login in its address shows one host and opens another, and one to a private address leads nowhere
| for a visitor but can reach their own network. Webhooks refuse both; so does every way of adding a destination.
*/

/** @return array<string, string> */
function dcRefused(): array
{
    return [
        'login and password' => 'https://user:pass@evil.example/',
        'a login' => 'https://paypal.com@evil.example/',
        'loopback' => 'http://127.0.0.1/',
        'localhost' => 'http://localhost/',
        'localhost with a dot' => 'http://localhost./',
        'a name under localhost' => 'http://foo.localhost/',
        'metadata' => 'http://169.254.169.254/latest/meta-data/',
        'IPv6 loopback' => 'http://[::1]/',
        'IPv4 in IPv6' => 'http://[::ffff:127.0.0.1]/',
        'hexadecimal' => 'http://0x7f000001/',
        'one number' => 'http://2130706433/',
        'octal' => 'http://0177.0.0.1/',
        'short form' => 'http://127.1/',
        'private 10' => 'http://10.0.0.1/',
        'private 192' => 'http://192.168.1.1/',
        'with a port' => 'http://192.168.1.1:8080/admin',
        'an internal name' => 'http://printer.local/',
    ];
}

/** @return array<string, string> */
function dcAllowed(): array
{
    return [
        'a public address' => 'https://example.com/ok',
        'a public IP' => 'http://8.8.8.8/',
        '@ in the path' => 'https://medium.com/@someone/post',
        '@ in the query' => 'https://example.com/?mail=a@b.example',
        '@ in the fragment' => 'https://example.com/#a@b',
        'a port' => 'https://example.com:8443/x',
        'a name that starts like a private address' => 'https://10.example.com/x',
        'local inside the name' => 'https://local.example.com/x',
    ];
}

it('refuses them in the form, on the guest page, in the API and in shorten-web', function () {
    Http::fake();
    $this->withoutMiddleware(ThrottleRequests::class);
    $user = User::factory()->create();
    $token = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create'])['plain_token']];
    foreach (dcRefused() as $name => $url) {
        $this->withHeaders($token)->postJson('/api/shorten', ['url' => $url])->assertStatus(422);
        $this->actingAs($user)->postJson('/links', ['target_url' => $url, 'status' => 'active'])->assertStatus(422)->assertJsonValidationErrors('target_url');
        $this->actingAs($user)->postJson('/api/shorten-web', ['url' => $url])->assertStatus(422);
        auth()->logout();
        $this->postJson('/shorten', ['target_url' => $url])->assertStatus(422);
        expect(Link::count())->toBe(0, $name);
    }
});

it('refuses them in the other destination fields of a link', function () {
    Http::fake();
    $user = User::factory()->create();
    foreach (['fallback_url' => 'https://user:pass@evil.example/', 'routing_rules' => [['url' => 'http://127.0.0.1/', 'countries' => ['US']]], 'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 50], ['url' => 'http://127.0.0.1/', 'weight' => 50]]] as $field => $value) {
        $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/ok', 'status' => 'active', $field => $value])->assertStatus(422);
    }
    expect(Link::count())->toBe(0);
});

it('marks them as errors in a CSV import', function () {
    Http::fake();
    $user = User::factory()->create();
    $rows = [];
    foreach (array_values(dcRefused()) as $i => $url) {
        $rows[] = ['line' => (string) ($i + 2), 'destination' => $url, 'slug' => '', 'tags' => '', 'folder' => '', 'expires_at' => '', 'max_clicks' => ''];
    }
    expect(array_unique(array_column(LinkImport::check($user, $rows), 'status')))->toBe(['error']);
});

it('refuses them in a bio block', function () {
    Http::fake();
    $user = User::factory()->create();
    BioPage::create(['user_id' => $user->id, 'slug' => 'dcpage', 'title' => 'x', 'theme' => 'default']);
    foreach (dcRefused() as $url) {
        $this->actingAs($user)->postJson('/bio/dcpage/links', ['type' => 'link', 'title' => 'n', 'url' => $url])->assertStatus(422);
    }
    expect(BioLink::count())->toBe(0);
    // a mail address has an @ and is no web address
    $this->actingAs($user)->postJson('/bio/dcpage/links', ['type' => 'link', 'title' => 'n', 'url' => 'mailto:me@example.com'])->assertSuccessful();
})->group('bio');

it('keeps accepting ordinary addresses', function () {
    Http::fake();
    $user = User::factory()->create();
    foreach (dcAllowed() as $name => $url) {
        $this->actingAs($user)->postJson('/api/shorten-web', ['url' => $url])->assertStatus(201);
    }
    expect(Link::count())->toBe(count(dcAllowed()));
});

it('says what is wrong', function () {
    Http::fake();
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://user:pass@evil.example/', 'status' => 'active'])
        ->assertJsonValidationErrors(['target_url' => 'The address cannot contain a user name or password.']);
    $this->actingAs($user)->postJson('/links', ['target_url' => 'http://127.0.0.1/', 'status' => 'active'])
        ->assertJsonValidationErrors(['target_url' => 'This address points to a private or internal network, which is not allowed.']);
});

it('marks a CSV row with an unclosed quote as an error, not as one long destination', function () {
    $parsed = LinkImport::parse("destination,slug,tags,folder,expires_at,max_clicks\n\"https://example.com/e,s4-csv-e,,,,\n");
    $checked = LinkImport::check(User::factory()->create(), $parsed['rows']);
    expect($checked[0]['status'])->toBe('error')
        ->and($checked[0]['problems'])->toContain(__('A quote in this row is not closed.'));
});

it('still reads a quoted destination with a comma in it', function () {
    $parsed = LinkImport::parse("destination,slug\n\"https://example.com/a,b\",csv-comma\n");
    $checked = LinkImport::check(User::factory()->create(), $parsed['rows']);
    expect($checked[0]['destination'])->toBe('https://example.com/a,b')->and($checked[0]['status'])->toBe('ok');
});
