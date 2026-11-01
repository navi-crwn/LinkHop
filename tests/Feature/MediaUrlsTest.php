<?php

use App\Http\Controllers\BrandAssetController;
use App\Mail\ShortLinkMigrationNotice;
use App\Models\Link;
use App\Models\User;
use App\Services\LinkService;
use App\Support\RemoteUrl;
use Hop\Bio\Models\BioLink;
use Hop\Bio\Models\BioPage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/*
 * Pages at hel.ink (short links, bio pages) are rendered on the public host while build/, storage/, images/ and
 * brand/ are served by the app host (link.hel.ink). Every file address on such a page must name that host.
 */
beforeEach(function () {
    config([
        'app.url' => 'https://link.hel.ink',
        'app.asset_url' => null,
        'shortener.public_url' => 'https://hel.ink',
        'hop.hub_url' => 'https://hub.hel.ink',
        'filesystems.disks.public.url' => 'https://link.hel.ink/storage',
    ]);
    URL::forceRootUrl('https://link.hel.ink');
    URL::forceScheme('https');
    Storage::forgetDisk('public');
});

/** Every file address a page asks the browser to load: src, srcset-less href on link/script, poster, CSS url(). */
function fileUrls(string $html): array
{
    $urls = [];
    preg_match_all('/<(?:img|script|source|video|audio|iframe)\b[^>]*?\bsrc\s*=\s*"([^"]*)"/i', $html, $m);
    $urls = array_merge($urls, $m[1]);
    preg_match_all('/<link\b[^>]*?\bhref\s*=\s*"([^"]*)"/i', $html, $m);
    $urls = array_merge($urls, $m[1]);
    preg_match_all('/<meta\b[^>]*?(?:og:image|twitter:image)[^>]*?content\s*=\s*"([^"]*)"/i', $html, $m);
    $urls = array_merge($urls, $m[1]);
    preg_match_all('/url\(\s*[\'"]?([^\'")]+)/i', $html, $m);
    $urls = array_merge($urls, $m[1]);

    return array_values(array_unique(array_map('html_entity_decode', $urls)));
}

function expectNoBareFileUrls(string $html): void
{
    foreach (fileUrls($html) as $url) {
        // A bare path ("/storage/a.png", "images/a.svg") is read against whichever host rendered the page.
        expect($url)->not->toMatch('#^(/(?!/)|(storage|images|brand|build|hop-home)/)#', "file address is host-relative: {$url}");
    }
}

function publicLink(string $slug, array $extra = []): Link
{
    return Link::create(array_merge([
        'user_id' => User::factory()->create()->id,
        'slug' => $slug,
        'target_url' => 'https://example.com/p',
        'status' => Link::STATUS_ACTIVE,
    ], $extra));
}

test('asset_host_url puts bare paths on the asset host and leaves absolute addresses alone', function () {
    expect(asset_host_url('/storage/bio-images/a.png'))->toBe('https://link.hel.ink/storage/bio-images/a.png')
        ->and(asset_host_url('images/brands/x.svg?v=1'))->toBe('https://link.hel.ink/images/brands/x.svg?v=1')
        ->and(asset_host_url('https://cdn.example/a.png'))->toBe('https://cdn.example/a.png')
        ->and(asset_host_url('//cdn.example/a.png'))->toBe('//cdn.example/a.png')
        ->and(asset_host_url('data:image/png;base64,AAAA'))->toBe('data:image/png;base64,AAAA')
        ->and(asset_host_url(null))->toBe('');

    config(['app.asset_url' => 'https://assets.hel.ink/']);

    expect(asset_host_url('/storage/a.png'))->toBe('https://assets.hel.ink/storage/a.png');
});

test('the pages a visitor sees at hel.ink/l/ only name files by absolute address', function () {
    publicLink('pw', ['password_hash' => bcrypt('x')]);
    publicLink('gate', ['content_warning' => 'sensitive']);
    publicLink('ended', ['expires_at' => now()->subDay()]);
    publicLink('og', ['custom_title' => 'T', 'custom_image_url' => 'https://img.example/x.png']);

    foreach (['/l/pw', '/l/gate', '/l/ended', '/l/nothere'] as $path) {
        $html = $this->get('https://hel.ink'.$path)->getContent();
        expect(fileUrls($html))->not->toBeEmpty();
        expectNoBareFileUrls($html);
    }
    $og = $this->withHeaders(['User-Agent' => 'facebookexternalhit/1.1'])->get('https://hel.ink/l/og')->assertOk();
    expect($og->getContent())->toContain('content="https://img.example/x.png"');
    // Built CSS and scripts come from the app host, not from the host that rendered the page.
    $ended = $this->get('https://hel.ink/l/ended')->getContent();
    expect($ended)->toContain('href="https://link.hel.ink/build/assets/')
        ->and($ended)->toContain('src="https://link.hel.ink/build/assets/')
        ->and($ended)->toContain('href="https://link.hel.ink/brand/');
});

test('the public layout and the other pages served at hel.ink name their files by absolute address', function () {
    foreach (['/', '/report-abuse', '/check', '/terms', '/l/a/b'] as $path) {
        expectNoBareFileUrls($this->get('https://hel.ink'.$path)->getContent());
    }
});

test('the content warning form posts to the host that served the page', function () {
    publicLink('gate2', ['content_warning' => 'sensitive']);

    $this->get('https://hel.ink/l/gate2')->assertOk()->assertSee('action="https://hel.ink/l/gate2"', false);
});

test('the content security policy lets a public-host page load files from the asset host', function () {
    $csp = (string) $this->get('https://hel.ink/l/nothere')->headers->get('Content-Security-Policy');

    foreach (['script-src', 'style-src', 'img-src', 'font-src', 'connect-src'] as $directive) {
        expect($csp)->toMatch('/'.$directive."[^;]*'self' https:\/\/link\.hel\.ink[ ;]/");
    }

    config(['app.asset_url' => 'https://assets.hel.ink']);
    $csp = (string) $this->get('https://hel.ink/l/nothere')->headers->get('Content-Security-Policy');

    expect($csp)->toMatch("/style-src[^;]*'self' https:\/\/assets\.hel\.ink[ ;]/")
        ->and($csp)->not->toMatch('/style-src[^;]*link\.hel\.ink/');
});

test('mail carries the brand logo as an absolute address on the app host', function () {
    $user = User::factory()->create();
    $html = (new ShortLinkMigrationNotice($user, collect()))->render();

    expectNoBareFileUrls($html);
    expect($html)->toContain('src="https://link.hel.ink/brand/');
})->group('bio');

test('the stored QR file is addressed on the app host', function () {
    $link = publicLink('qrme');
    app(LinkService::class)->generateQr($link);

    expect(Storage::disk('public')->url($link->fresh()->qr_code_path))->toBe('https://link.hel.ink/storage/qrcodes/qrme.png');
    Storage::disk('public')->delete('qrcodes/qrme.png');
});

test('a bio page at hel.ink/b/ loads uploads, icons and the background from the asset host', function () {
    $user = User::factory()->create();
    $page = BioPage::create([
        'user_id' => $user->id, 'slug' => 'me', 'title' => 'Me', 'theme' => 'lavender', 'is_published' => true,
        'avatar_url' => 'avatars/me.jpg', 'background_type' => 'image', 'background_image' => '/storage/bio-images/bg.png',
        'social_links' => [['platform' => 'github', 'url' => 'https://github.com/x']],
    ]);
    BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'A', 'url' => 'https://example.com', 'thumbnail_url' => '/storage/bio-thumbnails/t.png', 'brand' => 'instagram', 'is_active' => true, 'order' => 1]);
    BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'B', 'url' => 'https://example.com', 'custom_icon' => '/storage/bio-icons/i.png', 'is_active' => true, 'order' => 2]);
    BioLink::create(['bio_page_id' => $page->id, 'type' => 'image', 'title' => 'C', 'content' => '/storage/bio-images/c.png', 'is_active' => true, 'order' => 3]);

    $html = $this->get('https://hel.ink/b/me')->assertOk()->getContent();

    expectNoBareFileUrls($html);
    expect($html)->toContain('https://link.hel.ink/storage/avatars/me.jpg')
        ->and($html)->toContain('src="https://link.hel.ink/images/brands/github.svg?v=')
        ->and($html)->toContain('src="https://link.hel.ink/storage/bio-thumbnails/t.png"')
        ->and($html)->toContain('src="https://link.hel.ink/storage/bio-icons/i.png"')
        ->and($html)->toContain('src="https://link.hel.ink/storage/bio-images/c.png"')
        ->and($html)->toContain("url('https://link.hel.ink/storage/bio-images/bg.png')")
        // the share links name the page's public address, not the app host
        ->and($html)->toContain('value="https://hel.ink/b/me"')
        ->and($html)->not->toContain('link.hel.ink/b/me');
})->group('bio');

test('bio password and age pages post to the host that served them and come back to it', function () {
    $user = User::factory()->create();
    BioPage::create(['user_id' => $user->id, 'slug' => 'locked', 'title' => 'L', 'theme' => 'lavender', 'is_published' => true, 'password_enabled' => true, 'password' => bcrypt('pw')]);
    BioPage::create(['user_id' => $user->id, 'slug' => 'adult', 'title' => 'A', 'theme' => 'lavender', 'is_published' => true, 'is_adult_content' => true]);

    $this->get('https://hel.ink/b/locked')->assertOk()->assertSee('action="https://hel.ink/b/locked"', false);
    $this->post('https://hel.ink/b/locked', ['password' => 'pw'])->assertRedirect('https://hel.ink/b/locked');
    $this->get('https://hel.ink/b/adult')->assertOk()->assertSee('action="https://hel.ink/b/adult"', false);
    $this->post('https://hel.ink/b/adult', ['age_confirm' => 'yes'])->assertRedirect('https://hel.ink/b/adult');
})->group('bio');

test('the bio not-found page shows a logo that exists', function () {
    $html = $this->get('https://hel.ink/b/nothere')->assertNotFound()->getContent();

    expectNoBareFileUrls($html);
    preg_match('#src="https://link\.hel\.ink/brand/[a-f0-9]{8}/([^"]+)"#', $html, $m);
    expect($m[1] ?? null)->not->toBeNull();
    $this->get('https://link.hel.ink/brand/'.BrandAssetController::version().'/'.$m[1])->assertOk();
})->group('bio');

test('every brand file and asset() path the views name exists', function () {
    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('resources/views')));
    $bio = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('packages/bio/resources/views')));
    $checked = 0;
    foreach ([$views, $bio] as $files) {
        foreach ($files as $file) {
            if (! str_ends_with((string) $file, '.blade.php')) {
                continue;
            }
            $source = file_get_contents((string) $file);
            preg_match_all("/route\('brand\.asset',\s*'([^']+)'\)/", $source, $m);
            foreach ($m[1] as $name) {
                expect(is_file(resource_path('brand/'.$name)))->toBeTrue("{$file}: resources/brand/{$name} is missing");
                $checked++;
            }
            preg_match_all("/asset\('([^'.][^']*\.[a-z0-9]+)'\)/", $source, $m);
            foreach ($m[1] as $path) {
                expect(is_file(public_path($path)))->toBeTrue("{$file}: public/{$path} is missing");
                $checked++;
            }
        }
    }
    expect($checked)->toBeGreaterThan(3);
    foreach (config('brands.platforms') as $key => $platform) {
        expect(is_file(public_path('images/brands/'.$key.'.svg')))->toBeTrue("images/brands/{$key}.svg is missing");
        expect(is_file(public_path('images/brands/'.($platform['icon'] ?? $key.'.svg'))))->toBeTrue("icon of {$key} is missing");
    }
})->group('bio');

test('addresses found in another site\'s HTML are resolved against that page', function () {
    $page = 'https://example.com/blog/post?id=1';

    expect(RemoteUrl::resolve($page, '/img/og.png'))->toBe('https://example.com/img/og.png')
        ->and(RemoteUrl::resolve($page, '//cdn.example.net/og.png'))->toBe('https://cdn.example.net/og.png')
        ->and(RemoteUrl::resolve($page, 'og.png'))->toBe('https://example.com/blog/og.png')
        ->and(RemoteUrl::resolve($page, '?x=1'))->toBe('https://example.com/blog/post?x=1')
        ->and(RemoteUrl::resolve($page, 'https://cdn.example.net/og.png?a=1&amp;b=2'))->toBe('https://cdn.example.net/og.png?a=1&b=2')
        ->and(RemoteUrl::resolve('http://example.com:8080', '/a.png'))->toBe('http://example.com:8080/a.png')
        ->and(RemoteUrl::resolve($page, ''))->toBe('');
});

test('a profile photo is the hub\'s absolute address, never this app\'s storage', function () {
    $user = new User;
    $hub = 'https://hub.hel.ink/storage/avatars/me.jpg';

    $user->avatar_path = $hub;
    expect($user->avatarUrl())->toBe($hub);

    $user->avatar_path = 'avatars/me.jpg';
    expect($user->avatarUrl())->toBe($hub);

    $user->avatar_path = '/storage/avatars/me.jpg';
    expect($user->avatarUrl())->toBe($hub);

    $user->avatar_path = null;
    $user->avatar = $hub;
    expect($user->avatarUrl())->toBe($hub);

    $user->avatar = 'https://lh3.googleusercontent.com/a/x';
    expect($user->avatarUrl())->toBe('https://lh3.googleusercontent.com/a/x');

    $user->avatar = null;
    expect($user->avatarUrl())->toBeNull();
});
