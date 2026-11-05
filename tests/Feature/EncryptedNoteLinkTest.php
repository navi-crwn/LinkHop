<?php

use App\Mail\LinkDestinationBroken;
use App\Models\ActivityLog;
use App\Models\HopApp;
use App\Models\Link;
use App\Models\User;
use App\Services\LinkHopSafety;
use App\Support\DestinationChecker;
use App\Support\EncryptedNoteLink;
use Hop\Auth\Models\ApiToken;
use Hop\Bio\Models\BioLink;
use Hop\Bio\Models\BioPage;
use Hop\Safety\LinkSafety;
use Hop\Safety\UrlGuard;
use Hop\Ui\Locales;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| An encrypted NoteHop note keeps its key in the link after the "#". LinkHop must never store that address:
| these tests use made-up keys on the real NoteHop hosts (note.hel.ink, and the short host n.hel.ink).
*/

const NK_KEY_URL = 'https://note.hel.ink/AbC123#k=secretkey';
const NK_SHORT_URL = 'https://n.hel.ink/AbC123#k=secretkey';

function nkMessage(): string
{
    return EncryptedNoteLink::MESSAGE;
}

/** Nothing in the database may hold the made-up key. */
function nkNoKeyStored(): void
{
    expect(Link::where('target_url', 'like', '%secretkey%')->count())->toBe(0);
}

function nkUser(): User
{
    return User::factory()->create();
}

function nkToken(User $user): array
{
    return ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create'])['plain_token']];
}

// ---- what counts as an encrypted note link -------------------------------------------------------------------

it('recognises the addresses NoteHop hands out', function (string $url) {
    expect(app(EncryptedNoteLink::class)->matches($url))->toBeTrue();
})->with([
    'main host' => [NK_KEY_URL],
    'short host' => [NK_SHORT_URL],
    'old /n/ form' => ['https://note.hel.ink/n/AbC123#k=secretkey'],
    'owner link with the admin token' => ['https://note.hel.ink/AbC123#k=secretkey&a=admintoken'],
    'admin token alone' => ['https://note.hel.ink/AbC123#a=admintoken'],
    'key after another parameter' => ['https://n.hel.ink/AbC123#new=1&k=secretkey'],
    'percent-encoded parameter name, which URLSearchParams decodes' => ['https://n.hel.ink/AbC123#%6B=secretkey'],
    'upper case host and a trailing dot' => ['https://N.HEL.INK./AbC123#k=secretkey'],
    'a port' => ['https://note.hel.ink:443/AbC123#k=secretkey'],
    'http' => ['http://note.hel.ink/AbC123#k=secretkey'],
    'a www prefix' => ['https://www.note.hel.ink/AbC123#k=secretkey'],
    'user info in front of the host' => ['https://someone@note.hel.ink/AbC123#k=secretkey'],
    'a backslash, which a browser reads as the end of the host' => ['https://note.hel.ink\\@example.com/AbC123#k=secretkey'],
    'tabs inside the address, which a browser drops' => ["https://note.hel.ink/AbC123#k=secret\tkey"],
    'a query string before the fragment' => ['https://note.hel.ink/AbC123?x=1#k=secretkey'],
]);

it('leaves everything else alone', function (string $url) {
    expect(app(EncryptedNoteLink::class)->matches($url))->toBeFalse();
})->with([
    'a plain note address' => ['https://note.hel.ink/AbC123'],
    'the short host without a key' => ['https://n.hel.ink/AbC123'],
    'a fragment that is not a key' => ['https://note.hel.ink/faq#privacy'],
    'an empty key' => ['https://note.hel.ink/AbC123#k='],
    'a parameter NoteHop does not read' => ['https://note.hel.ink/AbC123#key=secretkey'],
    'another host with the same fragment' => ['https://example.com/AbC123#k=secretkey'],
    'a host that only starts with a NoteHop host' => ['https://note.hel.ink.example.com/AbC123#k=secretkey'],
    'a NoteHop host only named in the query' => ['https://example.com/?u=https://note.hel.ink/AbC123#k=secretkey'],
    'a NoteHop host only named in the user info' => ['https://note.hel.ink@example.com/AbC123#k=secretkey'],
    'a key in the query, which the server would see anyway' => ['https://note.hel.ink/AbC123?k=secretkey'],
    'not a web address' => ['mailto:me@note.hel.ink#k=secretkey'],
    'nothing' => [''],
]);

it('also knows a self-hosted NoteHop, its short domain and extra hosts from the settings', function () {
    config(['services.notehop.url' => 'https://notes.example.org', 'services.notehop.share_url' => 'https://nhop.example', 'hop.note_hosts' => ['note.hel.ink', 'n.hel.ink', 'secrets.example.net']]);
    $notes = app(EncryptedNoteLink::class);

    expect($notes->matches('https://notes.example.org/AbC123#k=secretkey'))->toBeTrue()
        ->and($notes->matches('https://nhop.example/AbC123#k=secretkey'))->toBeTrue()
        ->and($notes->matches('https://secrets.example.net/AbC123#k=secretkey'))->toBeTrue()
        ->and($notes->matches('https://other.example.org/AbC123#k=secretkey'))->toBeFalse();
});

it('also knows the NoteHop app registered in the apps table', function () {
    HopApp::create(['slug' => 'notehop', 'name' => 'NoteHop', 'url' => 'https://registered.example.org', 'callback_path' => '/cb', 'status' => HopApp::STATUS_ACTIVE, 'secret' => 'fake-secret']);

    expect(app(EncryptedNoteLink::class)->matches('https://registered.example.org/AbC123#k=secretkey'))->toBeTrue();
});

it('says why in plain words', function () {
    expect(nkMessage())->toContain('store that key on this server')->toContain('Basic note')->toContain('share button');
});

// ---- every way in ----------------------------------------------------------------------------------------------

it('refuses the key in the home page form', function (string $url) {
    $this->post('/shorten', ['target_url' => $url, 'cf-turnstile-response' => 'x'])->assertSessionHasErrors(['target_url' => nkMessage()]);
    expect(Link::count())->toBe(0);
})->with(['main host' => [NK_KEY_URL], 'short host' => [NK_SHORT_URL], 'typed without https://' => ['note.hel.ink/AbC123#k=secretkey']]);

it('takes a note address without a key and other fragments in the home page form', function (string $url) {
    $this->post('/shorten', ['target_url' => $url, 'cf-turnstile-response' => 'x'])->assertSessionHasNoErrors();
    expect(Link::where('target_url', $url)->count())->toBe(1);
})->with([
    'note without key' => ['https://note.hel.ink/AbC123'],
    'short host without key' => ['https://n.hel.ink/AbC123'],
    'another host, same fragment' => ['https://example.com/doc#k=notasecret'],
    'a NoteHop page anchor' => ['https://note.hel.ink/faq#privacy'],
]);

it('refuses the key when a signed-in user creates a link', function () {
    $user = nkUser();
    $this->actingAs($user)->postJson('/links', ['target_url' => NK_KEY_URL, 'status' => 'active'])
        ->assertStatus(422)->assertJsonPath('errors.target_url.0', nkMessage());
    $this->actingAs($user)->post('/links', ['target_url' => NK_SHORT_URL, 'status' => 'active'])->assertSessionHasErrors(['target_url' => nkMessage()]);
    nkNoKeyStored();
});

it('takes a note address without a key when a signed-in user creates a link', function () {
    $user = nkUser();
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://note.hel.ink/AbC123', 'status' => 'active', 'slug' => 'plainnote'])->assertOk();
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/a#k=x', 'status' => 'active', 'slug' => 'otherhost'])->assertOk();
    expect(Link::count())->toBe(2);
});

it('refuses the key when the destination of a link is edited', function () {
    $user = nkUser();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'editme', 'target_url' => 'https://example.com/ok', 'status' => 'active']);

    $this->actingAs($user)->put("/links/{$link->id}", ['target_url' => NK_KEY_URL, 'slug' => 'editme', 'status' => 'active'])
        ->assertSessionHasErrors(['target_url' => nkMessage()]);

    expect($link->fresh()->target_url)->toBe('https://example.com/ok');
    nkNoKeyStored();
});

it('refuses the key in the fallback address, a routing rule, a split variant and an https app address', function () {
    $user = nkUser();
    $base = ['target_url' => 'https://example.com/ok', 'status' => 'active'];

    $this->actingAs($user)->postJson('/links', $base + ['fallback_url' => NK_KEY_URL])->assertStatus(422)->assertJsonValidationErrors('fallback_url');
    $this->actingAs($user)->postJson('/links', $base + ['routing_rules' => [['country' => ['ID'], 'url' => NK_KEY_URL]]])->assertStatus(422)->assertJsonValidationErrors('routing_rules.0.url');
    $this->actingAs($user)->postJson('/links', $base + ['ab_variants' => [['url' => 'https://example.com/a', 'weight' => 50], ['url' => NK_SHORT_URL, 'weight' => 50]]])->assertStatus(422);
    $this->actingAs($user)->postJson('/links', $base + ['app_links' => ['mode' => 'custom', 'ios' => NK_KEY_URL]])->assertStatus(422);

    expect(Link::count())->toBe(0);
});

it('refuses the key in the token API', function () {
    $user = nkUser();
    $res = $this->withHeaders(nkToken($user))->postJson('/api/shorten', ['url' => NK_KEY_URL, 'alias' => 'apinote'])->assertStatus(422);

    expect($res->json('messages.url.0'))->toBe(nkMessage())->and(Link::count())->toBe(0);
    $this->withHeaders(nkToken($user))->postJson('/api/shorten', ['url' => 'https://note.hel.ink/AbC123', 'alias' => 'apiplain'])->assertCreated();
});

it('refuses the key in the dashboard API and the bio shortener', function () {
    $user = nkUser();
    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => NK_KEY_URL])->assertStatus(422)->assertJsonPath('errors.url.0', nkMessage());
    if (bio_enabled()) {
        $this->actingAs($user)->postJson('/api/bio/shorten', ['url' => NK_SHORT_URL])->assertStatus(422)->assertJsonPath('errors.url.0', nkMessage());
    }

    expect(Link::count())->toBe(0);
    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => 'https://note.hel.ink/AbC123'])->assertCreated();
});

it('refuses the key in a CSV import, in the preview and again when it is confirmed', function () {
    $user = nkUser();
    $csv = "destination,slug\n".NK_KEY_URL.",csvkey\nhttps://n.hel.ink/Plain1,csvplain\n";
    $res = $this->actingAs($user)->post('/links/import', ['file' => UploadedFile::fake()->createWithContent('links.csv', $csv)]);
    parse_str((string) parse_url($res->headers->get('Location'), PHP_URL_QUERY), $query);
    $rows = collect(Cache::get('linkimport:'.$user->id.':'.$query['preview']));

    expect($rows->pluck('status')->all())->toBe(['error', 'ok'])->and($rows[0]['messages'] ?? $rows[0]['problems'] ?? [])->toContain(nkMessage());
    $this->actingAs($user)->post('/links/import/confirm', ['token' => $query['preview']]);
    expect(Link::where('user_id', $user->id)->pluck('slug')->all())->toBe(['csvplain']);
    nkNoKeyStored();
});

it('refuses the key in a bio block', function () {
    if (! bio_enabled()) {
        $this->markTestSkipped('needs the bio package');
    }
    $user = nkUser();
    $page = BioPage::create(['user_id' => $user->id, 'slug' => 'notekeys', 'title' => 'x', 'theme' => 'default', 'is_published' => true]);

    $this->actingAs($user)->postJson("/bio/{$page->slug}/links", ['type' => 'link', 'title' => 'My note', 'url' => NK_KEY_URL])
        ->assertUnprocessable()->assertJsonValidationErrors('url');
    $this->actingAs($user)->postJson("/bio/{$page->slug}/links", ['type' => 'link', 'title' => 'My note', 'url' => 'https://note.hel.ink/AbC123'])->assertOk();
    expect(BioLink::where('url', 'like', '%secretkey%')->count())->toBe(0);
})->group('bio');

it('is the one rule behind LinkSafety, wherever the container hands it out', function () {
    $safety = app(LinkSafety::class);

    expect($safety)->toBeInstanceOf(LinkHopSafety::class)
        ->and($safety->rejectionReason(NK_KEY_URL))->toBe(nkMessage())
        ->and($safety->rejectionReason('https://note.hel.ink/AbC123'))->toBeNull();
});

it('keeps the message in all nine languages', function () {
    foreach (Locales::codes() as $code) {
        $catalog = json_decode(file_get_contents(base_path("lang/{$code}.json")), true);
        expect($catalog)->toHaveKey(EncryptedNoteLink::MESSAGE);
        if ($code !== 'en') {
            expect($catalog[EncryptedNoteLink::MESSAGE])->not->toBe(EncryptedNoteLink::MESSAGE);
        }
    }
    app()->setLocale('de');
    expect(app(EncryptedNoteLink::class)->message())->toContain('NoteHop')->not->toBe(EncryptedNoteLink::MESSAGE);
});

// ---- regression: the owner's check ---------------------------------------------------------------------------

it('does not let the key reach the database or a redirect, whichever way it is sent', function () {
    $user = nkUser();
    $this->post('/shorten', ['target_url' => NK_KEY_URL, 'cf-turnstile-response' => 'x']);
    $this->actingAs($user)->postJson('/links', ['target_url' => NK_SHORT_URL, 'status' => 'active']);
    $this->actingAs($user)->postJson('/api/shorten-web', ['url' => NK_KEY_URL]);
    $this->withHeaders(nkToken($user))->postJson('/api/shorten', ['url' => NK_SHORT_URL]);

    nkNoKeyStored();
    expect(Link::count())->toBe(0)->and(ActivityLog::where('description', 'like', '%secretkey%')->count())->toBe(0);
});

// ---- nothing is sent outside with a fragment -----------------------------------------------------------------

function nkGuard(): void
{
    app()->instance(UrlGuard::class, new class extends UrlGuard
    {
        protected function resolveHost(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
}

it('asks the destination without the fragment, and follows redirects without it too', function () {
    nkGuard();
    Http::fake([
        'dest.example/*' => Http::response('', 302, ['Location' => 'https://other.example/moved#k=secretkey']),
        'other.example/*' => Http::response('', 200),
    ]);

    $result = app(DestinationChecker::class)->check('https://dest.example/page#k=secretkey');

    expect($result['state'])->toBe(DestinationChecker::OK);
    Http::assertSentCount(2);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '#') || str_contains($r->url(), 'secretkey'));
    Http::assertSent(fn ($r) => $r->url() === 'https://other.example/moved');
});

it('sends the lookup services the address without its fragment', function () {
    config(['safety.providers.safe_browsing_key' => 'fake-key', 'safety.providers.spamhaus_dbl' => false, 'safety.providers.urlhaus_auth_key' => null]);
    Http::fake(['safebrowsing.googleapis.com/*' => Http::response([], 200)]);

    $result = app(LinkSafety::class)->assess('https://example.com/page#k=notarealkey', '', remote: true);

    expect($result['signals'])->toBe([]);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'safebrowsing')
        && $r->data()['threatInfo']['threatEntries'] === [['url' => 'https://example.com/page']]);
});

it('still catches a threat in the address through the lookup, and keeps the keyword score', function () {
    config(['safety.providers.safe_browsing_key' => 'fake-key', 'safety.providers.spamhaus_dbl' => false]);
    Http::fake(['safebrowsing.googleapis.com/*' => Http::response(['matches' => [['threatType' => 'MALWARE']]], 200)]);

    $result = app(LinkSafety::class)->assess('https://example.com/page#verify-account', '', remote: true);

    expect($result['signals'])->toContain('malware')->toContain('phishing')->and($result['score'])->toBe(180);
});

it('keeps the fragment out of the activity log and the broken-destination email', function () {
    $user = nkUser();
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/doc#k=notasecret', 'status' => 'active', 'slug' => 'logged'])->assertOk();

    expect(ActivityLog::where('description', 'like', '%notasecret%')->count())->toBe(0)
        ->and(ActivityLog::where('description', 'like', '%https://example.com/doc%')->count())->toBe(1);
    $link = Link::where('slug', 'logged')->first();
    $mail = (new LinkDestinationBroken($link, 404))->render();
    expect($mail)->toContain('https://example.com/doc')->not->toContain('notasecret');
});

it('treats an address without a host as no note link, on every PHP version', function (string $url) {
    // idn_to_ascii('') returns false on PHP 8.3 and throws a ValueError on PHP 8.5: the host is checked before it is mapped.
    $notes = app(EncryptedNoteLink::class);

    expect($notes->matches($url))->toBeFalse()
        ->and($notes->holdsKey($url))->toBeFalse()
        ->and(EncryptedNoteLink::scrubInput(['target_url' => $url]))->toBe(['target_url' => $url]);
})->with([
    'no host' => 'https://#k=secretkey',
    'only slashes' => 'https:///#k=secretkey',
    'fragment alone' => '#k=secretkey',
    'login without a host' => 'https://user@#k=secretkey',
    'port without a host' => 'https://:443/#k=secretkey',
    'empty brackets' => 'https://[]/#k=secretkey',
]);
