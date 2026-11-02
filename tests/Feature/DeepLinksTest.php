<?php

use App\Models\Link;
use App\Models\User;
use App\Support\DeepLinks;
use Hop\Auth\Models\ApiToken;

const DL_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1';
const DL_ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
const DL_DESKTOP = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15';

function dlLink(array $attrs = []): Link
{
    static $n = 0;
    $n++;

    return Link::create(array_merge(['user_id' => User::factory()->create()->id, 'slug' => 'deep'.$n, 'status' => 'active', 'target_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'app_links' => ['mode' => 'auto']], $attrs));
}

function dlVisit(Link $link, string $ua)
{
    return test()->withHeader('User-Agent', $ua)->get('/l/'.$link->slug);
}

it('recognises the places it knows and builds the app addresses', function (string $url, string $name, ?string $ios, ?string $androidPackage) {
    $auto = DeepLinks::auto($url);
    expect($auto['name'])->toBe($name);
    expect($auto['ios'] ?? null)->toBe($ios);
    if ($androidPackage) {
        expect($auto['android'])->toStartWith('intent://')->toContain('package='.$androidPackage)->toContain('S.browser_fallback_url='.rawurlencode($url));
    }
})->with([
    'youtube watch' => ['https://www.youtube.com/watch?v=abc123', 'YouTube', 'youtube://www.youtube.com/watch?v=abc123', 'com.google.android.youtube'],
    'youtu.be' => ['https://youtu.be/abc123', 'YouTube', 'youtube://youtu.be/abc123', 'com.google.android.youtube'],
    'spotify track' => ['https://open.spotify.com/track/4uLU6hMCjMI75M1A2tKUQC', 'Spotify', 'spotify:track:4uLU6hMCjMI75M1A2tKUQC', 'com.spotify.music'],
    'whatsapp' => ['https://wa.me/628123456789?text=Hello%20there', 'WhatsApp', 'whatsapp://send?phone=628123456789&text=Hello%20there', 'com.whatsapp'],
    'telegram' => ['https://t.me/hopfamily', 'Telegram', 'tg://resolve?domain=hopfamily', 'org.telegram.messenger'],
    'instagram profile' => ['https://www.instagram.com/helena.studio/', 'Instagram', 'instagram://user?username=helena.studio', 'com.instagram.android'],
    'x profile' => ['https://x.com/hopfamily', 'X', 'twitter://user?screen_name=hopfamily', 'com.twitter.android'],
    'x post' => ['https://twitter.com/hopfamily/status/1234567890123', 'X', 'twitter://status?id=1234567890123', 'com.twitter.android'],
    'zoom' => ['https://us02web.zoom.us/j/81234567890?pwd=abc', 'Zoom', 'zoomus://zoom.us/join?confno=81234567890&pwd=abc', 'us.zoom.videomeetings'],
    'app store' => ['https://apps.apple.com/id/app/example/id123456789', 'App Store', 'itms-apps://apps.apple.com/id/app/example/id123456789', null],
]);

it('knows google play and tiktok for Android only, and refuses what it cannot build', function () {
    expect(DeepLinks::auto('https://play.google.com/store/apps/details?id=com.example.app'))->toBe(['name' => 'Google Play', 'android' => 'market://details?id=com.example.app']);
    expect(DeepLinks::auto('https://www.tiktok.com/@helena')['android'])->toContain('package=com.zhiliaoapp.musically')->and(DeepLinks::auto('https://www.tiktok.com/@helena'))->not->toHaveKey('ios');
    // not a profile, not a known place, not a web address
    foreach (['https://www.instagram.com/p/abc/', 'https://example.com/page', 'https://x.com/home', 'ftp://youtube.com/watch?v=1', 'https://play.google.com/store/apps/details?id=bad id'] as $url) {
        expect(DeepLinks::auto($url))->toBeNull();
    }
});

it('sends an iPhone to a page that tries the app first, with the store as fallback when there is one', function () {
    $link = dlLink(['app_links' => ['mode' => 'auto', 'ios_store' => 'https://apps.apple.com/app/id544007664']]);
    $res = dlVisit($link, DL_IPHONE)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $res->assertSee('youtube://www.youtube.com/watch?v=dQw4w9WgXcQ', false)->assertSee('Opening YouTube')->assertSee('https://apps.apple.com/app/id544007664', false)->assertSee('Get the app');
    expect($res->headers->get('X-Robots-Tag'))->toBe('noindex');
});

it('sends an Android phone to its intent address, falling back to the web page', function () {
    $link = dlLink();
    $res = dlVisit($link, DL_ANDROID)->assertOk();
    $res->assertSee('intent://www.youtube.com/watch?v=dQw4w9WgXcQ#Intent;scheme=https;package=com.google.android.youtube;', false)->assertSee('Continue in the browser');
});

it('keeps desktops, crawlers and unknown destinations on the plain redirect', function () {
    $link = dlLink();
    dlVisit($link, DL_DESKTOP)->assertRedirect('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    dlVisit($link, 'WhatsApp/2.23.20.0 A')->assertRedirect('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    $unknown = dlLink(['target_url' => 'https://example.com/page']);
    dlVisit($unknown, DL_IPHONE)->assertRedirect('https://example.com/page');
    $off = dlLink(['app_links' => null]);
    dlVisit($off, DL_ANDROID)->assertRedirect('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

it('uses the addresses the owner typed in custom mode, per system', function () {
    $link = dlLink(['target_url' => 'https://example.com/item/42', 'app_links' => ['mode' => 'custom', 'ios' => 'myapp://item/42', 'android' => 'myapp://item/42', 'android_store' => 'market://details?id=com.example.app']]);
    dlVisit($link, DL_IPHONE)->assertOk()->assertSee('myapp://item/42', false)->assertSee('Continue in the browser');
    dlVisit($link, DL_ANDROID)->assertOk()->assertSee('market://details?id=com.example.app', false);
    // only one system given: the other keeps the plain redirect
    $iosOnly = dlLink(['target_url' => 'https://example.com/item/43', 'app_links' => ['mode' => 'custom', 'ios' => 'myapp://item/43']]);
    dlVisit($iosOnly, DL_ANDROID)->assertRedirect('https://example.com/item/43');
});

it('counts the visit like any other, and does not use a typed app address for a destination a rule chose', function () {
    $link = dlLink(['target_url' => 'https://example.com/item/42', 'max_clicks' => 5, 'app_links' => ['mode' => 'custom', 'ios' => 'myapp://item/42'], 'routing_rules' => [['country' => [], 'language' => [], 'device' => 'mobile', 'os' => 'ios', 'browser' => 'any', 'url' => 'https://www.youtube.com/watch?v=abc123']]]);
    $res = dlVisit($link, DL_IPHONE)->assertOk();
    // a rule chose the YouTube address: the typed address is not offered, the recognised one is
    $res->assertSee('youtube://www.youtube.com/watch?v=abc123', false)->assertDontSee('myapp://item/42', false);
    expect($link->fresh()->clicks)->toBe(1);
});

it('refuses app addresses that could dial, write, read or run script', function (string $address) {
    $problems = DeepLinks::problems(['mode' => 'custom', 'ios' => $address]);
    expect($problems)->toHaveKey('app_links.ios');
})->with(['javascript:alert(1)', 'tel:+62123', 'sms:+62123', 'mailto:a@b.c', 'file:///etc/passwd', 'data:text/html,x', 'http://example.com/a', 'my app://x', 'noscheme', 'intent://x#Intent;scheme=https;package=com.a.b;end']);

it('accepts normal app addresses and checks an intent address for a package and no components', function () {
    expect(DeepLinks::problems(['mode' => 'custom', 'ios' => 'myapp://item/1', 'android' => 'spotify:track:abc']))->toBe([]);
    expect(DeepLinks::problems(['mode' => 'custom', 'android' => 'intent://x/y#Intent;scheme=https;package=com.example.app;end']))->toBe([]);
    expect(DeepLinks::problems(['mode' => 'custom', 'android' => 'intent://x/y#Intent;scheme=https;end']))->toHaveKey('app_links.android');
    expect(DeepLinks::problems(['mode' => 'custom', 'android' => 'intent://x/y#Intent;scheme=https;package=com.example.app;component=com.example.app/.Evil;end']))->toHaveKey('app_links.android');
    expect(DeepLinks::problems(['mode' => 'custom', 'android' => 'intent://x/y#Intent;scheme=https;package=com.example.app;S.browser_fallback_url=http%3A%2F%2Fevil.test;end']))->toHaveKey('app_links.android');
    expect(DeepLinks::problems(['mode' => 'custom']))->toHaveKey('app_links.ios');
    expect(DeepLinks::problems(['mode' => 'auto', 'ios_store' => 'https://evil.test/app']))->toHaveKey('app_links.ios_store');
    expect(DeepLinks::problems(['mode' => 'auto', 'ios_store' => 'https://apps.apple.com/app/id1', 'android_store' => 'market://details?id=com.a.b']))->toBe([]);
});

it('is set from the create form and the edit form, and the list marks the link', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->postJson('/links', ['target_url' => 'https://youtu.be/abc123', 'status' => 'active', 'slug' => 'tube', 'app_links' => ['mode' => 'auto', 'ios_store' => 'https://apps.apple.com/app/id544007664']])->assertSuccessful();
    $link = Link::where('slug', 'tube')->firstOrFail();
    expect($link->app_links)->toBe(['mode' => 'auto', 'ios_store' => 'https://apps.apple.com/app/id544007664']);

    $this->get(route('links.edit', $link))->assertOk()->assertSee('Open in the app')->assertSee('This destination is recognised as YouTube.');
    $this->get('/links')->assertOk()->assertSee('title="Open in the app"', false);

    $this->postJson('/links', ['target_url' => 'https://example.com/x', 'status' => 'active', 'slug' => 'bad-app', 'app_links' => ['mode' => 'custom', 'ios' => 'javascript:alert(1)']])->assertStatus(422)->assertJsonValidationErrors('app_links.ios');
    $this->put(route('links.update', $link), ['target_url' => 'https://youtu.be/abc123', 'status' => 'active', 'slug' => 'tube', 'app_links' => ['mode' => 'off']])->assertRedirect();
    expect($link->fresh()->app_links)->toBeNull();
});

it('is accepted and returned by the API', function () {
    $user = User::factory()->create();
    $token = ApiToken::generate($user->id, 'cli', 100, null, ['link:create', 'link:read'])['plain_token'];
    $res = $this->withToken($token)->postJson('/api/shorten', ['url' => 'https://open.spotify.com/track/4uLU6hMCjMI75M1A2tKUQC', 'alias' => 'song', 'app_links' => ['mode' => 'auto']])->assertSuccessful();
    expect(Link::where('slug', 'song')->first()->app_links)->toBe(['mode' => 'auto']);
    $this->withToken($token)->postJson('/api/shorten', ['url' => 'https://example.com/a', 'alias' => 'bad-app2', 'app_links' => ['mode' => 'custom', 'android' => 'tel:123']])->assertStatus(422);
    $this->withToken($token)->getJson('/api/links/song')->assertOk()->assertJsonPath('data.app_links.mode', 'auto');
});
