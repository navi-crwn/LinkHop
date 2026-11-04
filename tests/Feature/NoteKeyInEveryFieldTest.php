<?php

use App\Models\Link;
use App\Models\User;
use App\Support\LinkImport;
use Hop\Auth\Models\ApiToken;
use Hop\Bio\Models\BioLink;
use Hop\Bio\Models\BioPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
| The key of an encrypted NoteHop note is refused in every field a person can fill in, not only in the destination:
| rich text, FAQ answers, block settings, page designs, QR settings, images and the warning text. The way a browser
| would read the address counts (HTML entities, CSS escapes, a line break inside it). Made-up keys.
*/

const EF_KEY = 'FAKEKEYzzz999';

/** @return array<string, string> */
function efAddresses(): array
{
    return [
        'plain' => 'https://note.hel.ink/Ab3dEf#k='.EF_KEY,
        'short host' => 'https://n.hel.ink/Ab3dEf#k='.EF_KEY,
        'owner token' => 'https://note.hel.ink/Ab3dEf#a='.EF_KEY,
        'full-width host' => "https://\u{FF4E}ote.hel.ink/Ab3dEf#k=".EF_KEY,
        'question mark' => 'https://note.hel.ink/Ab3dEf#?k='.EF_KEY,
        'no scheme' => 'note.hel.ink/Ab3dEf#k='.EF_KEY,
        'percent-encoded host' => 'https://no%74e.hel.ink/Ab3dEf#k='.EF_KEY,
        'intent fallback' => 'intent://x#Intent;scheme=https;S.browser_fallback_url=https%3A%2F%2Fnote.hel.ink%2FAb3dEf%23k%3D'.EF_KEY.';end',
    ];
}

function efPage(User $user, string $slug = 'efpage'): BioPage
{
    return BioPage::create(['user_id' => $user->id, 'slug' => $slug, 'title' => 'x', 'theme' => 'default', 'is_published' => true]);
}

/** Every string column of every table the requests below can write to, as "table.column" => rows holding the key. */
function efStored(): array
{
    $hits = [];
    foreach (['links', 'comments', 'bio_links', 'bio_pages'] as $table) {
        foreach (DB::table($table)->get() as $row) {
            foreach ((array) $row as $column => $value) {
                if (is_string($value) && str_contains($value, EF_KEY)) {
                    $hits["{$table}.{$column}"] = true;
                }
            }
        }
    }

    return array_keys($hits);
}

/** @return array<string, array{0: string, 1: Closure(string): array}> field name => how a block carries the address */
function efBlockFields(): array
{
    return [
        'html_content link' => ['html_content', fn ($u) => ['type' => 'html', 'html_content' => '<a href="'.$u.'">open</a>']],
        'html_content text' => ['html_content', fn ($u) => ['type' => 'html', 'html_content' => $u]],
        'code block' => ['html_content', fn ($u) => ['type' => 'code', 'html_content' => $u]],
        'faq answer' => ['faq_items', fn ($u) => ['type' => 'faq', 'faq_items' => [['question' => 'q', 'answer' => $u]]]],
        'faq question' => ['faq_items', fn ($u) => ['type' => 'faq', 'faq_items' => [['question' => $u, 'answer' => 'a']]]],
        'settings value' => ['settings', fn ($u) => ['type' => 'link', 'url' => 'https://example.com/ok', 'settings' => ['url' => $u]]],
        'settings nested' => ['settings', fn ($u) => ['type' => 'link', 'url' => 'https://example.com/ok', 'settings' => ['a' => ['b' => [$u]]]]],
        'settings key' => ['settings', fn ($u) => ['type' => 'link', 'url' => 'https://example.com/ok', 'settings' => [$u => 1]]],
        'map address' => ['map_address', fn ($u) => ['type' => 'map', 'map_address' => $u]],
        'countdown label' => ['countdown_label', fn ($u) => ['type' => 'countdown', 'countdown_label' => $u]],
        'vcard company' => ['vcard_company', fn ($u) => ['type' => 'vcard', 'vcard_company' => $u]],
    ];
}

it('refuses a key in any field of a bio block, new or edited', function () {
    Http::fake();
    $user = User::factory()->create();
    $page = efPage($user);
    $block = BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'keep', 'url' => 'https://example.com/ok', 'is_active' => true, 'order' => 0]);
    foreach (efBlockFields() as $name => [$field, $body]) {
        foreach (efAddresses() as $variant => $address) {
            $this->actingAs($user)->postJson('/bio/efpage/links', ['title' => 'n'] + $body($address))
                ->assertStatus(422)->assertJsonValidationErrors($field);
            $this->actingAs($user)->putJson("/bio/efpage/links/{$block->id}", ['title' => 'n'] + $body($address))
                ->assertStatus(422)->assertJsonValidationErrors($field);
            expect(efStored())->toBe([], "{$name} / {$variant}");
        }
    }
})->group('bio');

it('refuses a key in any field of a bio page', function () {
    Http::fake();
    $user = User::factory()->create();
    efPage($user);
    $fields = [
        'background_value' => fn ($u) => ['background_type' => 'image', 'background_value' => $u],
        'custom_css' => fn ($u) => ['custom_css' => 'body{background:url('.$u.')}'],
        'qr_settings' => fn ($u) => ['qr_settings' => ['logo' => $u]],
        'socials' => fn ($u) => ['socials' => [['platform' => 'website', 'url' => $u, 'value' => $u]]],
        'seo_title' => fn ($u) => ['seo_title' => mb_substr($u, 0, 47)],
    ];
    foreach ($fields as $field => $body) {
        foreach (efAddresses() as $variant => $address) {
            if ($field === 'seo_title' && mb_strlen($address) > 47) {
                continue;
            }
            // background_value only takes https images; a form it already refuses still has to be refused
            $this->actingAs($user)->putJson('/bio/efpage', ['title' => 'x'] + $body($address))->assertStatus(422);
            expect(efStored())->toBe([], "{$field} / {$variant}");
        }
    }
    // a bare handle: facebook.com/note.hel.ink/...#k=KEY still stores the key
    $this->actingAs($user)->putJson('/bio/efpage', ['title' => 'x', 'social_links' => [['platform' => 'facebook', 'value' => 'note.hel.ink/Ab3dEf#k='.EF_KEY, 'enabled' => true]]])
        ->assertStatus(422);
    expect(efStored())->toBe([]);
})->group('bio');

it('refuses the JSON qr_settings of a new bio page', function () {
    Http::fake();
    $user = User::factory()->create();
    foreach (efAddresses() as $variant => $address) {
        $this->actingAs($user)->postJson('/bio', ['slug' => 'efnew', 'title' => 'x', 'bio' => 'b', 'qr_settings' => json_encode(['logo' => $address])])
            ->assertStatus(422);
        expect(efStored())->toBe([], $variant);
    }
})->group('bio');

it('reads an address the way the browser would before it decides', function () {
    Http::fake();
    $user = User::factory()->create();
    efPage($user);
    $forms = [
        'decimal entity' => '<a href="https://note.hel.ink/Ab3dEf&#35;k='.EF_KEY.'">x</a>',
        'hex entity' => '<a href="https://note.hel.ink/Ab3dEf&#x23;k='.EF_KEY.'">x</a>',
        'named entity' => '<a href="https://note.hel.ink/Ab3dEf&num;k='.EF_KEY.'">x</a>',
        'amp entity' => '<a href="https://note.hel.ink/Ab3dEf#new=1&amp;k='.EF_KEY.'">x</a>',
        'entity in the host' => '<a href="https://note&#46;hel.ink/Ab3dEf#k='.EF_KEY.'">x</a>',
        'tab in the host' => "<a href=\"https://note.\thel.ink/Ab3dEf#k=".EF_KEY.'">x</a>',
        'line break in the fragment' => "<a href=\"https://note.hel.ink/Ab3dEf#k=\n".EF_KEY.'">x</a>',
        'single quotes' => "<a href='https://note.hel.ink/Ab3dEf#k=".EF_KEY."'>x</a>",
        'no quotes' => '<a href=https://note.hel.ink/Ab3dEf#k='.EF_KEY.'>x</a>',
        'protocol relative' => '<a href="//note.hel.ink/Ab3dEf#k='.EF_KEY.'">x</a>',
        'encoded twice in another address' => '<a href="https://example.com/?u=https%253A%252F%252Fnote.hel.ink%252FAb3dEf%2523k%253D'.EF_KEY.'">x</a>',
        'upper case' => '<A HREF="HTTPS://NOTE.HEL.INK/Ab3dEf#k='.EF_KEY.'">x</A>',
    ];
    foreach ($forms as $name => $html) {
        $this->actingAs($user)->postJson('/bio/efpage/links', ['type' => 'html', 'title' => 'h', 'html_content' => $html])
            ->assertStatus(422)->assertJsonValidationErrors('html_content');
        expect(efStored())->toBe([], $name);
    }
    $this->actingAs($user)->putJson('/bio/efpage', ['title' => 'x', 'custom_css' => 'a{background:url("https://note.hel.ink/Ab3dEf\\23 k='.EF_KEY.'")}'])
        ->assertStatus(422);
    expect(efStored())->toBe([]);
})->group('bio');

it('refuses a key in the free fields of a link, in the form, on the guest page and in the API', function () {
    Http::fake();
    $user = User::factory()->create();
    $token = ['Authorization' => 'Bearer '.ApiToken::generate($user->id, 'cli', 100, null, ['link:create'])['plain_token']];
    $n = 0;
    foreach (efAddresses() as $variant => $address) {
        foreach (['warning_reason', 'custom_title', 'custom_description', 'comment'] as $field) {
            $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/ok', 'status' => 'active', 'slug' => 'ef'.(++$n).'x', $field => $address])
                ->assertStatus(422)->assertJsonValidationErrors($field);
        }
        // these two are address-only fields: the plain url rule may answer first for a form it cannot read
        foreach (['custom_image_url', 'qr_logo_url'] as $field) {
            $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/ok', 'status' => 'active', 'slug' => 'ef'.(++$n).'x', $field => $address])
                ->assertStatus(422)->assertJsonValidationErrors($field);
        }
        auth()->logout();
        $this->postJson('/shorten', ['target_url' => 'https://example.com/ok', 'warning_reason' => $address])
            ->assertStatus(422)->assertJsonValidationErrors('warning_reason');
        $this->withHeaders($token)->postJson('/api/shorten', ['url' => 'https://example.com/ok', 'warning_reason' => $address])->assertStatus(422);
        expect(efStored())->toBe([], $variant);
    }
    expect(Link::count())->toBe(0);
});

it('refuses a key in a comment on a link, a folder name and a tag name', function () {
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'efcomment', 'target_url' => 'https://example.com/ok', 'status' => 'active']);
    $address = 'https://note.hel.ink/Ab3dEf#k='.EF_KEY;
    $this->actingAs($user)->postJson("/links/{$link->id}/comments", ['body' => 'see '.$address])->assertStatus(422)->assertJsonValidationErrors('body');
    $this->actingAs($user)->postJson('/folders', ['name' => $address])->assertStatus(422)->assertJsonValidationErrors('name');
    $this->actingAs($user)->postJson('/tags', ['name' => 'note.hel.ink/x#k='.EF_KEY])->assertStatus(422)->assertJsonValidationErrors('name');
    expect(efStored())->toBe([])
        ->and(DB::table('folders')->count())->toBe(0)
        ->and(DB::table('tags')->count())->toBe(0);
});

it('refuses a key in saved QR settings, a webhook address and the folder or tags of a CSV row', function () {
    Http::fake();
    $user = User::factory()->create();
    $link = Link::create(['user_id' => $user->id, 'slug' => 'efqr', 'target_url' => 'https://example.com/ok', 'status' => 'active']);
    $address = 'https://note.hel.ink/Ab3dEf#k='.EF_KEY;
    $this->actingAs($user)->postJson("/links/{$link->id}/qr/save", ['qr_image' => 'data:image/png;base64,AAAA', 'settings' => ['logo' => $address]])
        ->assertStatus(422)->assertJsonValidationErrors('settings');
    $this->actingAs($user)->postJson('/settings/webhooks', ['url' => $address])->assertStatus(422)->assertJsonValidationErrors('url');
    expect(efStored())->toBe([])->and(DB::table('webhook_endpoints')->count())->toBe(0);
    $rows = [['line' => '2', 'destination' => 'https://example.com/a', 'slug' => '', 'tags' => $address, 'folder' => '', 'expires_at' => '', 'max_clicks' => ''],
        ['line' => '3', 'destination' => 'https://example.com/b', 'slug' => '', 'tags' => '', 'folder' => $address, 'expires_at' => '', 'max_clicks' => '']];
    $checked = LinkImport::check($user, $rows);
    expect(array_column($checked, 'status'))->toBe(['error', 'error'])->and(json_encode($checked))->not->toContain(EF_KEY);
});

it('does not keep the key in the old input of a refused form', function () {
    Http::fake();
    $user = User::factory()->create();
    efPage($user);
    foreach (efAddresses() as $variant => $address) {
        // refused for the key itself
        $this->actingAs($user)->post('/bio', ['slug' => 'efnewpage', 'title' => 'x', 'bio' => 'see '.$address])->assertStatus(302)->assertSessionHasInput('title', 'x');
        expect(json_encode(session()->all()))->not->toContain(EF_KEY, "bio / {$variant}");
        // refused for something else, while another field holds a key
        $this->actingAs($user)->post('/bio', ['slug' => 'x', 'title' => 'x', 'bio' => 'see '.$address])->assertStatus(302)->assertSessionHasInput('title', 'x');
        expect(json_encode(session()->all()))->not->toContain(EF_KEY, "other error / {$variant}");
        $this->actingAs($user)->put('/bio/efpage', ['title' => 'x', 'custom_css' => 'a{background:url('.$address.')}'])->assertStatus(302)->assertSessionHasInput('title', 'x');
        expect(json_encode(session()->all()))->not->toContain(EF_KEY, "css / {$variant}");
        $this->actingAs($user)->post('/links', ['target_url' => 'https://example.com/ok', 'status' => 'active', 'warning_reason' => $address])->assertStatus(302)->assertSessionHasInput('status', 'active');
        expect(json_encode(session()->all()))->not->toContain(EF_KEY, "warning / {$variant}");
        session()->flush();
    }
})->group('bio');

it('keeps accepting a NoteHop link without a key', function () {
    Http::fake();
    $user = User::factory()->create();
    $page = efPage($user);
    $plain = 'https://note.hel.ink/Ab3dEf';
    // the destination itself, in the form and in the API
    $this->actingAs($user)->postJson('/links', ['target_url' => $plain, 'status' => 'active', 'slug' => 'efplain', 'warning_reason' => 'see '.$plain])->assertSuccessful();
    expect(Link::where('slug', 'efplain')->value('warning_reason'))->toBe('see '.$plain);
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/ok', 'status' => 'active', 'slug' => 'efimage', 'custom_image_url' => 'https://example.com/a.png#k=not-a-note', 'qr_logo_url' => 'https://example.com/b.png'])->assertSuccessful();
    // fragments that are not a key, and the same fragment on another host
    foreach (['https://note.hel.ink/Ab3dEf#new=1', 'https://note.hel.ink/Ab3dEf#k=', 'https://example.com/Ab3dEf#k='.EF_KEY, '<a href="#k=top">up</a>'] as $i => $value) {
        $this->actingAs($user)->postJson('/bio/efpage/links', ['type' => 'html', 'title' => 'h'.$i, 'html_content' => $value])->assertSuccessful();
    }
    $this->actingAs($user)->putJson('/bio/efpage', ['title' => 'x', 'custom_css' => 'a{background:url('.$plain.')}', 'qr_settings' => ['logo_url' => '/storage/qr-logos/a.png', 'color' => '#112233']])->assertSuccessful();
    expect(BioPage::find($page->id)->custom_css)->toContain($plain);
})->group('bio');

it('keeps accepting ordinary HTML, FAQ text and CSS', function () {
    Http::fake();
    $user = User::factory()->create();
    $page = efPage($user);
    $html = '<h2 id="k">Prices &amp; terms</h2><p style="color:#333">Tom &amp; Jerry &#35;1 &num;2</p><a href="https://example.com/a#section&amp;k=2">a</a><a href="/local#a=1">b</a><a href="mailto:me@example.com">c</a>';
    $this->actingAs($user)->postJson('/bio/efpage/links', ['type' => 'html', 'title' => 'h', 'html_content' => $html])->assertSuccessful();
    expect(BioLink::where('bio_page_id', $page->id)->value('html_content'))->toBe($html);
    $this->actingAs($user)->postJson('/bio/efpage/links', ['type' => 'faq', 'title' => 'f', 'faq_items' => [['question' => 'Colour #1?', 'answer' => 'Use a=1 & k=2, see https://example.com/#k=2']]])->assertSuccessful();
    $css = '.a::before{content:"\\23 x"}#k{color:#123456}.b{background:url(https://example.com/i.png#a=1)}';
    $this->actingAs($user)->putJson('/bio/efpage', ['title' => 'x', 'custom_css' => $css])->assertSuccessful();
    expect(BioPage::find($page->id)->custom_css)->toBe($css);
})->group('bio');

it('takes the same hosts as the destination rule, the registered NoteHop app included', function () {
    Http::fake();
    config(['services.notehop.share_url' => 'https://notes.example.org']);
    app()->forgetScopedInstances();
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/links', ['target_url' => 'https://example.com/ok', 'status' => 'active', 'warning_reason' => 'https://notes.example.org/x#k='.EF_KEY])
        ->assertStatus(422)->assertJsonValidationErrors('warning_reason');
});
