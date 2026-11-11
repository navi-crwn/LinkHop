<?php

use App\Models\Link;
use App\Models\User;
use App\Services\LinkService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| links:scrub-note-keys leaves no key behind in a column that can hold one, says what it did per column, and leaves
| everything that is not a key alone. Rows are written straight to the database, the way an old row looks. Made-up keys.
*/

const SE_KEY = 'FAKEKEYzzz999';

/** Columns a person cannot write free text into, or that are never about an address: anything else must be scrubbed. */
const SE_NOT_FREE_TEXT = [
    'links' => ['slug', 'status', 'safety_status', 'creator_hash', 'password_hash', 'redirect_type', 'qr_code_path', 'qr_fg_color', 'qr_bg_color', 'content_warning', 'target_url', 'fallback_url', 'routing_rules', 'ab_variants', 'app_links', 'wa_mode', 'claim_token_hash'],
    'bio_links' => ['type', 'public_id', 'btn_bg_color', 'btn_text_color', 'btn_border_color', 'entrance_animation', 'attention_animation'],
    'bio_pages' => ['slug', 'password', 'theme', 'layout', 'background_type', 'text_color', 'font_family', 'block_shape', 'block_shadow', 'social_icon_color', 'social_placement', 'social_icons_position', 'title_color', 'bio_color', 'link_bg_color', 'link_text_color', 'header_bg_color', 'background_color', 'background_gradient', 'button_bg_color', 'button_text_color', 'button_shape', 'button_shadow', 'socials_position', 'google_analytics_id', 'facebook_pixel_id', 'avatar_shape', 'badge', 'badge_color', 'tiktok_pixel_id', 'hover_effect', 'background_animation', 'favicon'],
    'abuse_reports' => ['slug', 'url', 'email', 'ip_address', 'status'],
];

/** @return array{link: int, page: int, block: int, report: int, comment: int, folder: int, tag: int, endpoint: int} */
function seRows(User $user): array
{
    $now = now();
    $base = ['created_at' => $now, 'updated_at' => $now];
    $link = DB::table('links')->insertGetId(['user_id' => $user->id, 'slug' => 'selink', 'target_url' => 'https://example.com/ok', 'status' => 'active', 'redirect_type' => '302'] + $base);
    $page = DB::table('bio_pages')->insertGetId(['user_id' => $user->id, 'slug' => 'sepage', 'title' => 't', 'theme' => 'default', 'is_published' => 1] + $base);
    $block = DB::table('bio_links')->insertGetId(['bio_page_id' => $page, 'type' => 'link', 'title' => 't', 'is_active' => 1, 'order' => 0] + $base);
    $report = DB::table('abuse_reports')->insertGetId(['url' => 'https://example.com/x', 'email' => 'r@example.com', 'reason' => 'r', 'status' => 'pending'] + $base);
    $comment = DB::table('comments')->insertGetId(['link_id' => $link, 'user_id' => $user->id, 'body' => 'b'] + $base);
    $folder = DB::table('folders')->insertGetId(['user_id' => $user->id, 'name' => 'n'] + $base);
    $tag = DB::table('tags')->insertGetId(['user_id' => $user->id, 'name' => 'n'] + $base);
    $endpoint = DB::table('webhook_endpoints')->insertGetId(['user_id' => $user->id, 'url' => 'https://hook.example/x', 'secret' => 's', 'is_active' => 1] + $base);

    return compact('link', 'page', 'block', 'report', 'comment', 'folder', 'tag', 'endpoint');
}

/** @return list<array{0: string, 1: string}> every text column to check, as [table, column] */
function seColumns(): array
{
    $columns = [['comments', 'body'], ['folders', 'name'], ['tags', 'name'], ['webhook_endpoints', 'url']];
    foreach (SE_NOT_FREE_TEXT as $table => $skip) {
        foreach (Schema::getColumns($table) as $column) {
            if (preg_match('/char|text|json|clob/i', $column['type']) && ! in_array($column['name'], $skip, true)) {
                $columns[] = [$table, $column['name']];
            }
        }
    }

    return $columns;
}

function seId(array $ids, string $table): int
{
    return ['links' => $ids['link'], 'bio_pages' => $ids['page'], 'bio_links' => $ids['block'], 'abuse_reports' => $ids['report'], 'comments' => $ids['comment'], 'folders' => $ids['folder'], 'tags' => $ids['tag'], 'webhook_endpoints' => $ids['endpoint']][$table];
}

it('names the free-text columns it has to clean', function () {
    expect(seColumns())->toContain(['bio_pages', 'social_links'], ['bio_pages', 'background_image'], ['bio_pages', 'background_value'], ['bio_pages', 'custom_css'], ['bio_pages', 'socials'], ['bio_pages', 'qr_settings'],
        ['bio_links', 'html_content'], ['bio_links', 'faq_items'], ['bio_links', 'settings'], ['links', 'custom_image_url'], ['links', 'qr_logo_url'], ['links', 'warning_reason'], ['abuse_reports', 'reason']);
})->group('bio');

it('cuts the key out of every free-text column, whatever form the address has', function (string $value) {
    $ids = seRows(User::factory()->create());
    foreach (seColumns() as [$table, $column]) {
        DB::table($table)->where('id', seId($ids, $table))->update([$column => $value]);
    }
    Artisan::call('links:scrub-note-keys', ['--apply' => true]);
    $output = Artisan::output();
    expect($output)->not->toContain(SE_KEY);
    foreach (seColumns() as [$table, $column]) {
        expect((string) DB::table($table)->where('id', seId($ids, $table))->value($column))->not->toContain(SE_KEY, "{$table}.{$column}");
        expect($output)->toContain("{$table}.{$column} rows cleaned: 1");
    }
})->with([
    'an address' => 'https://note.hel.ink/Ab3dEf#k='.SE_KEY,
    'inside a sentence' => 'see https://n.hel.ink/Ab3dEf#a='.SE_KEY.' please',
    'no scheme' => 'note.hel.ink/Ab3dEf#k='.SE_KEY,
    'in a link' => '<a href="https://note.hel.ink/Ab3dEf#k='.SE_KEY.'">x</a>',
    'written with an entity' => '<a href="https://note.hel.ink/Ab3dEf&#35;k='.SE_KEY.'">x</a>',
    'in a stylesheet' => 'a{background:url(https://note.hel.ink/Ab3dEf#k='.SE_KEY.')}',
    'with a CSS escape' => 'a{background:url(https://note.hel.ink/Ab3dEf\\23 k='.SE_KEY.')}',
    'in JSON' => json_encode([['platform' => 'website', 'value' => 'https://note.hel.ink/Ab3dEf#k='.SE_KEY, 'enabled' => true]]),
    'in JSON, full-width host' => json_encode(['a' => ['b' => "https://\u{FF4E}ote.hel.ink/Ab3dEf#k=".SE_KEY]]),
    'in JSON, no scheme' => json_encode(['note.hel.ink/Ab3dEf#k='.SE_KEY]),
    'as a JSON key' => json_encode(['https://note.hel.ink/Ab3dEf#k='.SE_KEY => 1]),
])->group('bio');

it('keeps the rest of the value, and JSON stays the JSON Laravel reads', function () {
    $user = User::factory()->create();
    $ids = seRows($user);
    DB::table('bio_pages')->where('id', $ids['page'])->update([
        'social_links' => json_encode([['platform' => 'website', 'value' => 'https://note.hel.ink/Ab3dEf#k='.SE_KEY, 'enabled' => true]]),
        'custom_css' => '#top{color:#123} a{background:url(https://note.hel.ink/Ab3dEf#new=1&k='.SE_KEY.')}',
    ]);
    DB::table('bio_links')->where('id', $ids['block'])->update(['html_content' => '<p>Tom &amp; Jerry</p><a href="https://note.hel.ink/Ab3dEf#k='.SE_KEY.'">x</a>']);
    Artisan::call('links:scrub-note-keys', ['--apply' => true]);
    $page = DB::table('bio_pages')->where('id', $ids['page'])->first();
    expect(json_decode($page->social_links, true))->toBe([['platform' => 'website', 'value' => 'https://note.hel.ink/Ab3dEf', 'enabled' => true]])
        ->and($page->custom_css)->toBe('#top{color:#123} a{background:url(https://note.hel.ink/Ab3dEf#new=1)}')
        ->and(DB::table('bio_links')->where('id', $ids['block'])->value('html_content'))->toBe('<p>Tom &amp; Jerry</p><a href="https://note.hel.ink/Ab3dEf">x</a>');
})->group('bio');

it('leaves what is not a key alone, and does not count it', function () {
    $ids = seRows(User::factory()->create());
    $values = [
        'custom_css' => '.a::before{content:"\\23 x"}#k{color:#123} .b{background:url(https://example.com/i.png#k=1)}',
        'bio' => 'Number #1 at https://note.hel.ink/Ab3dEf and https://example.com/Ab3dEf#k='.SE_KEY,
        'social_links' => json_encode(['https://note.hel.ink/Ab3dEf#new=1']),
    ];
    DB::table('bio_pages')->where('id', $ids['page'])->update($values);
    Artisan::call('links:scrub-note-keys', ['--apply' => true]);
    $page = DB::table('bio_pages')->where('id', $ids['page'])->first();
    foreach ($values as $column => $value) {
        expect($page->{$column})->toBe($value);
        expect(Artisan::output())->not->toContain("bio_pages.{$column} rows cleaned: 1");
    }
})->group('bio');

it('counts in a dry run and changes nothing', function () {
    $ids = seRows(User::factory()->create());
    $value = 'https://note.hel.ink/Ab3dEf#k='.SE_KEY;
    DB::table('bio_pages')->where('id', $ids['page'])->update(['custom_css' => $value]);
    DB::table('abuse_reports')->where('id', $ids['report'])->update(['reason' => $value]);
    Artisan::call('links:scrub-note-keys');
    $output = Artisan::output();
    expect($output)->toContain('bio_pages.custom_css rows would be cleaned: 1')->toContain('abuse_reports.reason rows would be cleaned: 1')
        ->and(DB::table('bio_pages')->where('id', $ids['page'])->value('custom_css'))->toBe($value)
        ->and(DB::table('abuse_reports')->where('id', $ids['report'])->value('reason'))->toBe($value);
})->group('bio');

it('keeps a link live when only its picture or text held a key, and drops its cached copy', function () {
    $user = User::factory()->create();
    $ids = seRows($user);
    DB::table('links')->where('id', $ids['link'])->update(['custom_image_url' => 'https://note.hel.ink/Ab3dEf#k='.SE_KEY]);
    Cache::put(app(LinkService::class)->cacheKey('selink'), 'cached copy', 300);
    Artisan::call('links:scrub-note-keys', ['--apply' => true]);
    expect(Link::find($ids['link'])->status)->toBe('active')
        ->and(Cache::has(app(LinkService::class)->cacheKey('selink')))->toBeFalse()
        ->and(DB::table('activity_logs')->where('action', 'disabled')->count())->toBe(0);
})->group('bio');

it('says so when a key could not be cut out, with the row but not the key', function () {
    $ids = seRows(User::factory()->create());
    // encoded twice inside another address: seen, but its fragment cannot be told from the rest
    $value = 'https://example.com/?u=https%253A%252F%252Fnote.hel.ink%252FAb3dEf%2523k%253D'.SE_KEY;
    DB::table('bio_pages')->where('id', $ids['page'])->update(['bio' => $value]);
    Artisan::call('links:scrub-note-keys', ['--apply' => true]);
    expect(Artisan::output())->toContain("bio_pages.bio: 1 rows still hold a key that could not be cut out (ids: {$ids['page']}). Edit them by hand.")
        ->not->toContain(SE_KEY);
})->group('bio');
