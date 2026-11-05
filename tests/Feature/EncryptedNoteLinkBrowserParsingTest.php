<?php

use App\Models\AbuseReport;
use App\Models\Link;
use App\Models\User;
use App\Support\DestinationChecker;
use App\Support\EncryptedNoteLink;
use Hop\Auth\Models\ApiToken;
use Hop\Bio\Models\BioLink;
use Hop\Bio\Models\BioPage;
use Hop\Safety\UrlGuard;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
| The note rule has to see an address the way a browser does. Every address below opens the real NoteHop host in a
| browser (checked with the WHATWG URL parser) and carries a made-up key. Only made-up keys are used.
*/

const BP_KEY = 'FAKEKEYzzz999';

/** Addresses a browser sends to a NoteHop host with a key (label => address). */
function bpOpensNote(): array
{
    $k = BP_KEY;

    return [
        'one slash after the scheme' => "https:/note.hel.ink/Ab3dEf#k=$k",
        'no slash after the scheme' => "https:note.hel.ink/Ab3dEf#k=$k",
        'three slashes' => "https:///note.hel.ink/Ab3dEf#k=$k",
        'four slashes' => "https:////note.hel.ink/Ab3dEf#k=$k",
        'backslashes' => "https:\\\\note.hel.ink\\Ab3dEf#k=$k",
        'a tab inside the scheme' => "ht\ttps://note.hel.ink/Ab3dEf#k=$k",
        'a leading control character' => "\x01https://note.hel.ink/Ab3dEf#k=$k",
        'another leading control character' => "\x1fhttps://note.hel.ink/Ab3dEf#k=$k",
        'fullwidth n' => "https://\u{FF4E}ote.hel.ink/Ab3dEf#k=$k",
        'fullwidth host with fullwidth dots' => "https://\u{FF4E}\u{FF4F}\u{FF54}\u{FF45}\u{FF0E}\u{FF48}\u{FF45}\u{FF4C}\u{FF0E}\u{FF49}\u{FF4E}\u{FF4B}/Ab3dEf#k=$k",
        'circled n' => "https://\u{24DD}ote.hel.ink/Ab3dEf#k=$k",
        'double-struck N' => "https://\u{2115}ote.hel.ink/Ab3dEf#k=$k",
        'ideographic full stop' => "https://note\u{3002}hel\u{3002}ink/Ab3dEf#k=$k",
        'soft hyphen' => "https://no\u{AD}te.hel.ink/Ab3dEf#k=$k",
        'zero-width space' => "https://no\u{200B}te.hel.ink/Ab3dEf#k=$k",
        'percent-encoded dot' => "https://note%2ehel.ink/Ab3dEf#k=$k",
        'percent-encoded letter' => "https://no%74e.hel.ink/Ab3dEf#k=$k",
        'a question mark before the key, which URLSearchParams drops' => "https://note.hel.ink/Ab3dEf#?k=$k",
        'the same on the short host' => "https://n.hel.ink/Ab3dEf#?k=$k",
        'a question mark before the owner token' => "https://note.hel.ink/Ab3dEf#?a=$k",
    ];
}

it('sees a NoteHop address the way a browser does', function (string $url) {
    expect(app(EncryptedNoteLink::class)->matches($url))->toBeTrue();
})->with(bpOpensNote());

it('still leaves alone what a browser does not send to NoteHop with a key', function (string $url) {
    expect(app(EncryptedNoteLink::class)->matches($url))->toBeFalse();
})->with([
    'a plus sign before the name' => ['https://note.hel.ink/Ab3dEf#+k=FAKEKEYzzz999'],
    'two question marks' => ['https://note.hel.ink/Ab3dEf#??k=FAKEKEYzzz999'],
    'a name that only ends in k' => ['https://note.hel.ink/Ab3dEf#xk=FAKEKEYzzz999'],
    'a second # swallows the key' => ['https://note.hel.ink/Ab3dEf#x#k=FAKEKEYzzz999'],
    'a subdomain of a NoteHop host' => ['https://evil.note.hel.ink/Ab3dEf#k=FAKEKEYzzz999'],
    'a look-alike label' => ['https://xn--note-.hel.ink/Ab3dEf#k=FAKEKEYzzz999'],
    'the note host only in the user info' => ['https://note.hel.ink@example.com/Ab3dEf#k=FAKEKEYzzz999'],
    'the note host after a backslash in the user info' => ['https://example.com\\@note.hel.ink/Ab3dEf#k=FAKEKEYzzz999'],
    'a zero-width joiner, which makes the address invalid in a browser' => ["https://no\u{200D}te.hel.ink/Ab3dEf#k=FAKEKEYzzz999"],
]);

it('compares a self-hosted NoteHop domain the same way', function () {
    config(['hop.note_hosts' => ['note.hel.ink', 'n.hel.ink', 'Geheim.Example.NET.']]);
    $notes = app(EncryptedNoteLink::class);

    expect($notes->matches('https://geheim.example.net/Ab3dEf#k=FAKEKEYzzz999'))->toBeTrue()
        ->and($notes->matches("https://geheim\u{FF0E}example.net/Ab3dEf#k=FAKEKEYzzz999"))->toBeTrue();
});

it('is built on ext-intl, which composer.json requires', function () {
    expect(function_exists('idn_to_ascii'))->toBeTrue()
        ->and(json_decode((string) file_get_contents(base_path('composer.json')), true)['require'])->toHaveKey('ext-intl');
});

// ---- every way in -------------------------------------------------------------------------------------------

function bpNoKeyStored(): void
{
    expect(Link::where('target_url', 'like', '%'.BP_KEY.'%')->orWhere('fallback_url', 'like', '%'.BP_KEY.'%')->count())->toBe(0);
}

function bpUser(): User
{
    return User::factory()->create();
}

function bpGuard(): void
{
    Http::fake(['*' => Http::response('', 200)]);
    app()->bind(UrlGuard::class, fn () => new class extends UrlGuard
    {
        protected function resolveHost(string $host): array
        {
            return filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ['93.184.216.34'];
        }
    });
}

it('refuses them in the home page form', function (string $url) {
    bpGuard();
    $this->post('/shorten', ['target_url' => $url, 'cf-turnstile-response' => 'x']);
    expect(Link::count())->toBe(0);
})->with(array_map(fn ($u) => [$u], bpOpensNote()));

it('refuses them when a signed-in user creates or edits a link, in the address and in the fallback', function (string $url) {
    bpGuard();
    $user = bpUser();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'editme', 'target_url' => 'https://example.com/ok', 'status' => 'active']);
    $this->actingAs($user)->postJson('/links', ['target_url' => $url, 'status' => 'active', 'slug' => 'newone']);
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/ok', 'status' => 'active', 'slug' => 'newtwo', 'fallback_url' => $url]);
    $this->actingAs($user)->putJson("/links/{$link->id}", ['target_url' => $url, 'slug' => 'editme', 'status' => 'active']);
    $this->actingAs($user)->putJson("/links/{$link->id}", ['target_url' => 'https://example.com/ok', 'slug' => 'editme', 'status' => 'active', 'fallback_url' => $url]);

    bpNoKeyStored();
    expect(Link::count())->toBe(1);
})->with(array_map(fn ($u) => [$u], bpOpensNote()));

it('refuses them in the token API and the dashboard API', function (string $url) {
    bpGuard();
    $user = bpUser();
    $token = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create'])['plain_token']];
    $this->withHeaders($token)->postJson('/api/shorten', ['url' => $url, 'alias' => 'apione']);
    $this->withHeaders($token)->postJson('/api/shorten', ['url' => 'https://example.com/ok', 'alias' => 'apitwo', 'fallback_url' => $url]);
    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => $url]);
    if (bio_enabled()) {
        $this->actingAs($user)->postJson('/api/bio/shorten', ['url' => $url]);
        $this->actingAs($user)->postJson('/bio/shorten', ['url' => $url]);
    }

    bpNoKeyStored();
    expect(Link::count())->toBe(0);
})->with(array_map(fn ($u) => [$u], bpOpensNote()));

it('refuses them in routing rules, split variants and app addresses', function (string $url) {
    bpGuard();
    $user = bpUser();
    $base = ['target_url' => 'https://example.com/ok', 'status' => 'active'];
    $this->actingAs($user)->postJson('/links', $base + ['slug' => 'routeone', 'routing_rules' => [['country' => ['ID'], 'url' => $url]]]);
    $this->actingAs($user)->postJson('/links', $base + ['slug' => 'splitone', 'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 50], ['url' => $url, 'weight' => 50]]]);
    $this->actingAs($user)->postJson('/links', $base + ['slug' => 'appone', 'app_links' => ['mode' => 'custom', 'ios' => $url]]);

    expect(Link::where('routing_rules', 'like', '%'.BP_KEY.'%')->orWhere('ab_variants', 'like', '%'.BP_KEY.'%')->orWhere('app_links', 'like', '%'.BP_KEY.'%')->count())->toBe(0);
})->with(array_map(fn ($u) => [$u], bpOpensNote()));

it('refuses them in a CSV import', function (string $url) {
    bpGuard();
    $user = bpUser();
    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, ['destination', 'slug']);
    fputcsv($fh, [$url, 'csvkey']);
    rewind($fh);
    $res = $this->actingAs($user)->post('/links/import', ['file' => UploadedFile::fake()->createWithContent('l.csv', stream_get_contents($fh))]);
    parse_str((string) parse_url((string) $res->headers->get('Location'), PHP_URL_QUERY), $q);
    $this->actingAs($user)->post('/links/import/confirm', ['token' => $q['preview'] ?? 'none']);

    bpNoKeyStored();
    expect(Link::count())->toBe(0);
})->with(array_map(fn ($u) => [$u], bpOpensNote()));

it('refuses them in a bio block', function (string $url) {
    if (! bio_enabled()) {
        $this->markTestSkipped('needs the bio package');
    }
    bpGuard();
    $user = bpUser();
    $page = BioPage::create(['user_id' => $user->id, 'slug' => 'bpbio', 'title' => 'x', 'theme' => 'default', 'is_published' => true]);
    $block = BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'a', 'url' => 'https://example.com/ok', 'is_active' => true, 'order' => 0]);
    $this->actingAs($user)->postJson('/bio/bpbio/links', ['type' => 'link', 'title' => 'n', 'url' => $url]);
    $this->actingAs($user)->postJson('/bio/bpbio/links', ['type' => 'link', 'title' => 'n', 'url' => 'https://example.com/ok', 'embed_url' => $url]);
    $this->actingAs($user)->putJson("/bio/bpbio/links/{$block->id}", ['type' => 'link', 'title' => 'a', 'url' => $url]);

    expect(BioLink::where('url', 'like', '%'.BP_KEY.'%')->orWhere('embed_url', 'like', '%'.BP_KEY.'%')->count())->toBe(0);
})->group('bio')->with(array_map(fn ($u) => [$u], bpOpensNote()));

// ---- the copy in the session (old input) --------------------------------------------------------------------

it('does not flash the key back to the session when a form is refused', function (string $field) {
    bpGuard();
    $user = bpUser();
    $url = 'https://note.hel.ink/Ab3dEf#k='.BP_KEY;
    $this->actingAs($user)->post('/links', ['target_url' => $url, 'status' => 'active', 'slug' => 'oldinput', 'fallback_url' => $url, 'custom_title' => 'My note'])
        ->assertSessionHasErrors('target_url');

    expect(json_encode(session()->all()))->not->toContain(BP_KEY)
        ->and(session()->getOldInput('target_url'))->toBe('https://note.hel.ink/Ab3dEf')
        ->and(session()->getOldInput('custom_title'))->toBe('My note');
})->with(['dashboard form' => ['target_url']]);

it('does not flash the key back from the guest form either', function () {
    bpGuard();
    $this->post('/shorten', ['target_url' => 'https://n.hel.ink/Ab3dEf#k='.BP_KEY, 'cf-turnstile-response' => 'x'])->assertSessionHasErrors('target_url');

    expect(json_encode(session()->all()))->not->toContain(BP_KEY)
        ->and(session()->getOldInput('target_url'))->toBe('https://n.hel.ink/Ab3dEf');
});

it('keeps what was typed when the refusal has another reason', function () {
    bpGuard();
    $this->post('/shorten', ['target_url' => 'https://example.com/a#k=notakey', 'slug' => 'a b', 'cf-turnstile-response' => 'x'])->assertSessionHasErrors('slug');

    expect(session()->getOldInput('target_url'))->toBe('https://example.com/a#k=notakey');
});

it('does not flash the key back when the CSRF token has expired', function () {
    Route::middleware('web')->post('/_expired', fn () => throw new TokenMismatchException('expired'));
    $this->post('/_expired', ['target_url' => 'https://note.hel.ink/Ab3dEf#k='.BP_KEY, 'title' => 'kept'])->assertRedirect();

    expect(json_encode(session()->all()))->not->toContain(BP_KEY)
        ->and(session()->getOldInput('title'))->toBe('kept');
});

it('does not keep the key in the CSV preview that waits in the cache', function (string $url) {
    bpGuard();
    $user = bpUser();
    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, ['destination', 'slug']);
    fputcsv($fh, [$url, 'csvwait']);
    rewind($fh);
    $res = $this->actingAs($user)->post('/links/import', ['file' => UploadedFile::fake()->createWithContent('l.csv', stream_get_contents($fh))]);
    parse_str((string) parse_url((string) $res->headers->get('Location'), PHP_URL_QUERY), $q);
    $rows = Cache::get('linkimport:'.$user->id.':'.($q['preview'] ?? 'none'));

    expect($rows)->toBeArray()->and(json_encode($rows))->not->toContain(BP_KEY)
        ->and($rows[0]['status'])->toBe('error');
})->with(['plain' => ['https://note.hel.ink/Ab3dEf#k='.BP_KEY], 'full-width host' => ["https://\u{FF4E}ote.hel.ink/Ab3dEf#k=".BP_KEY], 'one slash' => ['https:/note.hel.ink/Ab3dEf#?k='.BP_KEY]]);

it('refuses a key on a host from HOP_NOTE_HOSTS, however the host is written', function (string $url) {
    bpGuard();
    config(['hop.note_hosts' => ['note.hel.ink', 'n.hel.ink', 'secrets.example.net']]);
    $user = bpUser();
    $token = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create'])['plain_token']];

    $this->withHeaders($token)->postJson('/api/shorten', ['url' => $url, 'alias' => 'selfhosted'])->assertStatus(422);

    bpNoKeyStored();
})->with([
    'plain' => ['https://secrets.example.net/Ab3dEf#k='.BP_KEY],
    'upper case and a dot' => ['https://SECRETS.example.NET./Ab3dEf#k='.BP_KEY],
    'full-width s' => ["https://\u{FF53}ecrets.example.net/Ab3dEf#k=".BP_KEY],
    'question mark before the key' => ['https://secrets.example.net/Ab3dEf#?k='.BP_KEY],
]);

it('does not follow or keep the fragment of a redirect that a third host sends the monitor to', function () {
    bpGuard();
    Http::swap(new Factory);
    Http::fake([
        'third.example/*' => Http::response('', 302, ['Location' => 'https://note.hel.ink/Ab3dEf#k='.BP_KEY]),
        'note.hel.ink/*' => Http::response('', 200),
    ]);

    $result = app(DestinationChecker::class)->check('https://third.example/go');

    expect($result['state'])->toBe(DestinationChecker::OK);
    Http::assertSent(fn ($request) => $request->url() === 'https://note.hel.ink/Ab3dEf');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '#') || str_contains($request->url(), BP_KEY));
});

// ---- the key inside an Android intent: the browser fallback is opened in a browser ----------------------------

function bpIntent(string $inner): string
{
    return 'intent://open/item#Intent;scheme=myapp;package=com.example.app;S.browser_fallback_url='.rawurlencode($inner).';end';
}

it('sees a key in the browser fallback of an Android intent', function (string $inner, bool $refused) {
    expect(app(EncryptedNoteLink::class)->matches(bpIntent($inner)))->toBe($refused);
})->with([
    'a note address with a key' => ['https://note.hel.ink/Ab3dEf#k='.BP_KEY, true],
    'a full-width host' => ["https://\u{FF4E}ote.hel.ink/Ab3dEf#?k=".BP_KEY, true],
    'a note address without a key' => ['https://note.hel.ink/Ab3dEf', false],
    'another site' => ['https://example.com/Ab3dEf#k='.BP_KEY, false],
]);

it('refuses such an intent as an app address, in the dashboard and in the token API', function () {
    bpGuard();
    $user = bpUser();
    $token = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create'])['plain_token']];
    $intent = bpIntent('https://note.hel.ink/Ab3dEf#k='.BP_KEY);

    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/ok', 'status' => 'active', 'slug' => 'intentone', 'app_links' => ['mode' => 'custom', 'android' => $intent]])
        ->assertStatus(422)->assertJsonValidationErrors('app_links.android');
    $this->withHeaders($token)->postJson('/api/shorten', ['url' => 'https://example.com/ok', 'alias' => 'intenttwo', 'app_links' => ['mode' => 'custom', 'android' => $intent]])->assertStatus(422);
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/ok', 'status' => 'active', 'slug' => 'intentok', 'app_links' => ['mode' => 'custom', 'android' => bpIntent('https://example.com/fallback')]])->assertOk();

    expect(Link::where('app_links', 'like', '%'.rawurlencode(BP_KEY).'%')->orWhere('app_links', 'like', '%'.BP_KEY.'%')->count())->toBe(0);
});

it('keeps the key out of an abuse report, the mail to the admin and the form sent back', function () {
    $url = 'https://note.hel.ink/Ab3dEf#k='.BP_KEY;
    $this->post('/report-abuse', ['url' => $url, 'email' => 'r@example.com', 'reason' => 'too short', 'cf-turnstile-response' => 'x']);
    expect(json_encode(session()->all()))->not->toContain(BP_KEY);

    $this->post('/report-abuse', ['url' => $url, 'email' => 'r@example.com', 'reason' => 'This hides a private note: '.$url, 'cf-turnstile-response' => 'x']);
    $mails = collect(app('mailer')->getSymfonyTransport()->messages())->map(fn ($m) => $m->toString())->implode("\n");

    expect(AbuseReport::count())->toBe(1)
        ->and(AbuseReport::where('url', 'like', '%'.BP_KEY.'%')->orWhere('reason', 'like', '%'.BP_KEY.'%')->count())->toBe(0)
        ->and($mails)->not->toContain(BP_KEY)->and($mails)->not->toBe('');
});
