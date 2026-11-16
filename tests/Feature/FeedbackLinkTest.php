<?php

use App\Models\Link;
use App\Models\User;
use App\Support\Feedback;
use Hop\Ui\Locales;

/*
| The Feedback link only opens the HOP ID form in a new tab: {hub}/feedback?app=linkhop&from=<screen>&v=<version>.
| This app sends and stores nothing. The screen name is a route name or template, never a slug, id or query.
*/

/** @return list<array{url:string, query:array<string,string>, tab:string, rel:string, text:string}> */
function feedbackLinks(string $html): array
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR);
    $out = [];
    foreach ((new DOMXPath($dom))->query('//a[contains(@href, "/feedback?")]') as $a) {
        $url = html_entity_decode($a->getAttribute('href'));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $out[] = ['url' => $url, 'query' => $query, 'tab' => $a->getAttribute('target'), 'rel' => $a->getAttribute('rel'), 'text' => trim($a->textContent)];
    }

    return $out;
}

test('every dashboard screen links to the hub form in a new tab with the screen name and the version', function () {
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'secretslug', 'target_url' => 'https://example.com/private?token=abc', 'status' => 'active']);
    $screens = [
        '/dashboard' => 'dashboard', '/links' => 'links.index', '/analytics' => 'analytics', '/settings' => 'settings',
        '/qr-codes' => 'qr-codes.index', '/links/'.$link->id.'/edit' => 'links.edit', '/links/'.$link->id => 'links.show',
    ];
    foreach ($screens as $path => $name) {
        $html = $this->actingAs($user)->get($path)->assertOk()->getContent();
        $links = feedbackLinks($html);
        expect($links)->toHaveCount(1, $path);
        $l = $links[0];
        expect($l['url'])->toStartWith('https://hub.hel.ink/feedback?app=linkhop&from=')
            ->and($l['tab'])->toBe('_blank')->and($l['rel'])->toBe('noopener')->and($l['text'])->toBe('Feedback')
            ->and($l['query']['app'])->toBe('linkhop')
            ->and($l['query']['v'])->toBe(json_decode(file_get_contents(resource_path('changelog.json')), true)['sources']['LinkHop'])
            ->and(array_keys($l['query']))->toBe(['app', 'from', 'v'])
            ->and($l['query']['from'])->toBe($name);
        expect($l['url'])->not->toContain('secretslug')->not->toContain((string) $link->id)->not->toContain('token')
            ->and($l['query']['from'])->toMatch('~^[A-Za-z0-9._:/{}-]{1,80}$~');
    }
});

test('the public pages with the shared footer show one Feedback link next to Help, with the screen name', function () {
    $pages = ['/' => 'home', '/help' => 'pages.help', '/faq' => 'pages.faq', '/about' => 'pages.about', '/features' => 'pages.features',
        '/docs' => 'pages.docs', '/terms' => 'pages.terms', '/privacy' => 'pages.privacy', '/contact' => 'pages.contact', '/status' => 'pages.status', '/qr' => 'qr'];
    foreach ($pages as $path => $name) {
        $footer = array_values(array_filter(feedbackLinks($this->get($path)->assertOk()->getContent()), fn ($l) => $l['text'] === 'Feedback'));
        expect($footer)->toHaveCount(1, $path)
            ->and($footer[0]['query']['from'])->toBe($name)->and($footer[0]['tab'])->toBe('_blank')->and($footer[0]['rel'])->toBe('noopener');
    }
});

test('the link checker, which has a footer of its own with Help, shows Feedback too, and the abuse form gets none', function () {
    $footer = array_values(array_filter(feedbackLinks($this->get('/check')->assertOk()->getContent()), fn ($l) => $l['text'] === 'Feedback'));
    expect($footer)->toHaveCount(1)->and($footer[0]['query']['from'])->toBe('link.check')->and($footer[0]['tab'])->toBe('_blank');
    // The abuse form is a page of its own without a Help link, so it has no Feedback link either.
    $this->get('/report-abuse')->assertOk()->assertDontSee('/feedback?', false);
});

test('Help and Contact carry one Send feedback link besides the footer one', function () {
    foreach (['/help', '/contact'] as $path) {
        $links = feedbackLinks($this->get($path)->assertOk()->getContent());
        expect(array_column($links, 'text'))->toContain('Send feedback')->toContain('Feedback')
            ->and(count(array_filter($links, fn ($l) => $l['text'] === 'Send feedback')))->toBe(1);
        foreach ($links as $l) {
            expect($l['tab'])->toBe('_blank')->and($l['rel'])->toBe('noopener');
        }
    }
});

test('the pages that belong to a short link get no Feedback link', function () {
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'gone', 'target_url' => 'https://example.com/a', 'status' => 'active', 'expires_at' => now()->subMinute()]);
    Link::create(['user_id' => $user->id, 'slug' => 'locked', 'target_url' => 'https://example.com/b', 'status' => 'active', 'password' => 'secret-pass']);
    foreach (['/l/gone', '/l/nosuchlink', '/l/locked'] as $path) {
        $res = $this->get($path);
        expect($res->getContent())->not->toContain('/feedback?', $path)->and($res->getContent())->not->toContain('The Feedback link opens');
    }
    // An address this app does not know is an app page, so it keeps the footer link.
    expect(feedbackLinks($this->get('/dashboard/does-not-exist')->assertNotFound()->getContent()))->toHaveCount(1);
});

test('without a hub address no page has a Feedback link or the Privacy sentence', function () {
    config(['hop.hub_url' => '']);
    $user = User::factory()->create();
    foreach (['/', '/help', '/contact', '/privacy', '/faq', '/check', '/qr'] as $path) {
        $html = $this->get($path)->assertOk()->getContent();
        expect($html)->not->toContain('/feedback')->and($html)->not->toContain('The Feedback link opens')->and($html)->not->toContain('Send feedback');
    }
    expect($this->actingAs($user)->get('/dashboard')->getContent())->not->toContain('/feedback');
});

test('the Privacy page says what the Feedback link does, linking the hub policy, without naming hel.ink in it', function () {
    $html = $this->get('/privacy')->assertOk()->getContent();
    expect($html)->toContain('The Feedback link opens a form on the HOP ID site this app is connected to. This app receives nothing from that form; what the form stores and for how long is described in the <a href="https://hub.hel.ink/privacy" rel="noopener">HOP ID privacy policy</a>.');
    config(['hop.hub_url' => 'https://id.example.org/']);
    expect($this->get('/privacy')->getContent())->toContain('<a href="https://id.example.org/privacy" rel="noopener">HOP ID privacy policy</a>');
});

test('Feedback, Send feedback and the Privacy sentence are translated in every language', function (string $code) {
    $d = json_decode(file_get_contents(base_path("lang/{$code}.json")), true);
    $sentence = 'The Feedback link opens a form on the HOP ID site this app is connected to. This app receives nothing from that form; what the form stores and for how long is described in the :link.';
    foreach (['Feedback', 'Send feedback', $sentence, 'HOP ID privacy policy'] as $key) {
        expect($d[$key] ?? '')->not->toBe('');
        if ($code !== 'en' && $key !== 'Feedback') {
            expect($d[$key])->not->toBe($key);
        }
    }
    expect($d[$sentence])->toContain(':link')->not->toContain('hel.ink');
    $this->withUnencryptedCookie('hop_lang', $code)->get('/privacy')->assertOk()->assertSee(explode(':link', $d[$sentence])[0], false);
    $this->withUnencryptedCookie('hop_lang', $code)->get('/help')->assertOk()->assertSee('>'.e($d['Send feedback']).'</a>', false);
    $this->withUnencryptedCookie('hop_lang', $code)->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertSee('>'.e($d['Feedback']).'</a>', false);
})->with(fn () => Locales::codes());

test('a hub address that is not http(s) gives no link at all', function () {
    expect(Feedback::url())->toStartWith('https://hub.hel.ink/feedback?app=linkhop');
    foreach (['', 'hub.test', 'javascript:alert(1)', 'ftp://x.test'] as $bad) {
        config(['hop.hub_url' => $bad]);
        expect(Feedback::url())->toBe('', $bad);
    }
});
