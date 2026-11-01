<?php

use App\Models\Folder;
use App\Models\Link;
use App\Models\RetiredSlug;
use App\Models\Tag;
use App\Models\User;
use App\Support\LinkImport;
use App\Support\WebhookGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function csvFile(string $content, string $name = 'links.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

/** Uploads, follows to the preview page and returns [token, response]. */
function importPreview($test, User $user, string $csv)
{
    $res = $test->actingAs($user)->post('/links/import', ['file' => csvFile($csv)]);
    $res->assertRedirect();
    $location = $res->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    return [$query['preview'] ?? null, $test->actingAs($user)->get($location)];
}

test('the sample file is a CSV a user can download and upload as it is', function () {
    $user = User::factory()->create();
    $res = $this->actingAs($user)->get('/links/import/sample')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="linkhop-import-sample.csv"');
    expect($res->getContent())->toStartWith('destination,slug,tags,folder,expires_at,max_clicks');
    [$token, $page] = importPreview($this, $user, $res->getContent());
    $page->assertOk()->assertSee('Preview');
    expect(collect(Cache::get('linkimport:'.$user->id.':'.$token))->pluck('status')->all())->toBe(['ok', 'ok', 'ok']);
});

test('the import page needs a signed-in user', function () {
    $this->get('/links/import')->assertRedirect();
    $this->post('/links/import', [])->assertRedirect();
});

test('preview shows every row with its verdict and creates nothing', function () {
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'taken-one', 'target_url' => 'https://example.com/exists', 'status' => 'active']);
    $csv = implode("\n", [
        'destination,slug,tags,folder,expires_at,max_clicks',
        'https://example.com/good,good-one,a;b,Promo,2030-01-01 10:00,50',
        'example.org/no-scheme,,,,,',
        'not a url,,,,,',
        'https://example.com/slug-taken,taken-one,,,,',
        'https://example.com/reserved,api,,,,',
        'https://example.com/badslug,ab,,,,',
        'https://example.com/underscore,my_slug,,,,',
        'https://example.com/past,,,,2001-01-01,',
        'https://example.com/zero,,,,,0',
        'https://example.com/exists,,,,,',
        'https://example.com/good,,,,,',
    ]);
    [, $page] = importPreview($this, $user, $csv);
    $page->assertOk()->assertSee('The destination is not a valid web address.')->assertSee('The slug is already taken.')->assertSee('This slug is reserved.')
        ->assertSee('The slug needs at least 3 characters.')->assertSee('Slug can only contain letters, numbers, and hyphens.')
        ->assertSee('The expiry date has to be in the future.')->assertSee('The click limit has to be at least 1.')
        ->assertSee('You already have a link to this address.')->assertSee('The same address is on row 2.');
    expect(Link::where('user_id', $user->id)->count())->toBe(1)->and(Folder::where('name', 'Promo')->exists())->toBeFalse()->and(Tag::count())->toBe(0);
});

test('confirming creates the good rows with folders, tags, limits and a generated slug', function () {
    $user = User::factory()->create();
    Folder::create(['user_id' => $user->id, 'name' => 'Default', 'is_default' => true]);
    $csv = "destination,slug,tags,folder,expires_at,max_clicks\nhttps://example.com/one,one-slug,red;Blue,Promo,2031-02-03 04:05,25\nexample.com/two,,red,,,\nnot a url,,,,,\n";
    [$token] = importPreview($this, $user, $csv);
    $res = $this->actingAs($user)->post('/links/import/confirm', ['token' => $token]);
    $res->assertRedirect(route('links.import'));
    $one = Link::where('slug', 'one-slug')->first();
    expect($one->target_url)->toBe('https://example.com/one')->and($one->max_clicks)->toBe(25)->and($one->expires_at->format('Y-m-d H:i'))->toBe('2031-02-03 04:05')
        ->and($one->folder->name)->toBe('Promo')->and($one->tags->pluck('name')->sort()->values()->all())->toBe(['Blue', 'red'])->and($one->status)->toBe('active');
    $two = Link::where('target_url', 'https://example.com/two')->first();
    expect($two->slug)->toHaveLength(6)->and((bool) $two->folder->is_default)->toBeTrue()->and($two->tags->pluck('name')->all())->toBe(['red']);
    expect(Link::where('user_id', $user->id)->count())->toBe(2)->and(Tag::where('name', 'red')->count())->toBe(1);
    $this->actingAs($user)->get(route('links.import'))->assertSee('2 links were created.')->assertSee('1 row was skipped')->assertSee('Skipped: the row has a problem.');
    // the preview is used up
    $this->actingAs($user)->post('/links/import/confirm', ['token' => $token])->assertSessionHasErrors('file');
});

test('duplicates are skipped unless the owner asks for them', function () {
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'dup-src', 'target_url' => 'https://example.com/dup', 'status' => 'active']);
    $csv = "destination\nhttps://EXAMPLE.com/dup/\nhttps://example.com/new\n";
    [$token] = importPreview($this, $user, $csv);
    $this->actingAs($user)->post('/links/import/confirm', ['token' => $token]);
    expect(Link::where('user_id', $user->id)->count())->toBe(2);
    [$token] = importPreview($this, $user, $csv);
    $this->actingAs($user)->post('/links/import/confirm', ['token' => $token, 'allow_duplicates' => '1']);
    expect(Link::where('user_id', $user->id)->count())->toBe(4);
});

test('more than 500 rows is refused with a clear message', function () {
    $user = User::factory()->create();
    $csv = "destination\n".collect(range(1, 501))->map(fn ($i) => "https://example.com/p{$i}")->implode("\n");
    $this->actingAs($user)->post('/links/import', ['file' => csvFile($csv)])->assertSessionHasErrors(['file' => 'The file has more than 500 rows. Split it into smaller files.']);
    $ok = "destination\n".collect(range(1, 500))->map(fn ($i) => "https://example.com/p{$i}")->implode("\n");
    $this->actingAs($user)->post('/links/import', ['file' => csvFile($ok)])->assertSessionHasNoErrors();
});

test('bad files are explained: no header, empty, wrong type, too big', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/links/import', ['file' => csvFile("url_here,other\nhttps://example.com,x\n")])->assertSessionHasErrors('file');
    $this->actingAs($user)->post('/links/import', ['file' => csvFile("destination\n")])->assertSessionHasErrors(['file' => 'The file has a header row but no links.']);
    $this->actingAs($user)->post('/links/import', ['file' => csvFile('')])->assertSessionHasErrors('file');
    $this->actingAs($user)->post('/links/import', ['file' => csvFile('destination', 'links.exe')])->assertSessionHasErrors('file');
    $this->actingAs($user)->post('/links/import', ['file' => UploadedFile::fake()->create('big.csv', 600)])->assertSessionHasErrors('file');
    $this->actingAs($user)->post('/links/import', [])->assertSessionHasErrors('file');
});

test('semicolons, a byte-order mark, Excel encoding, column aliases and blank lines are understood', function () {
    $parsed = LinkImport::parse("\xEF\xBB\xBFURL;Alias;Tags\r\nhttps://example.com/a;aaa;x|y\r\n\r\n;;\r\nhttps://example.com/b;;\r\n");
    expect($parsed['error'])->toBeNull()->and($parsed['rows'])->toHaveCount(2)
        ->and($parsed['rows'][0])->toMatchArray(['destination' => 'https://example.com/a', 'slug' => 'aaa', 'tags' => 'x|y', 'line' => '2'])
        ->and($parsed['rows'][1]['line'])->toBe('5');
    $latin = LinkImport::parse(mb_convert_encoding("destination,folder\nhttps://example.com/c,Caf\u{e9}\n", 'Windows-1252', 'UTF-8'));
    expect($latin['rows'][0]['folder'])->toBe("Caf\u{e9}");
    $quoted = LinkImport::parse("destination,tags\n\"https://example.com/d?a=1,2\",\"p,q\"\n");
    expect($quoted['rows'][0]['destination'])->toBe('https://example.com/d?a=1,2')->and($quoted['rows'][0]['tags'])->toBe('p,q');
});

test('the same rules as the form: retired slugs, the same slug twice, and unsafe destinations', function () {
    $user = User::factory()->create();
    RetiredSlug::retire('old-one');
    $csv = "destination,slug\nhttps://example.com/1,twice\nhttps://example.com/2,twice\nhttps://example.com/3,old-one\njavascript:alert(1),\n";
    [$token] = importPreview($this, $user, $csv);
    $rows = Cache::get('linkimport:'.$user->id.':'.$token);
    expect(collect($rows)->pluck('status')->all())->toBe(['ok', 'error', 'error', 'error']);
    expect($rows[1]['problems'][0])->toContain('used twice')->and($rows[2]['problems'][0])->toBe('The slug is already taken.');
});

test('rows beyond the daily allowance are flagged and not created', function () {
    config(['limits.user.max_links_per_day' => 3]);
    $user = User::factory()->create();
    Link::create(['user_id' => $user->id, 'slug' => 'before', 'target_url' => 'https://example.com/before', 'status' => 'active']);
    $csv = "destination\nhttps://example.com/q1\nhttps://example.com/q2\nhttps://example.com/q3\n";
    [$token, $page] = importPreview($this, $user, $csv);
    $page->assertSee('Your link allowance is used up');
    expect(collect(Cache::get('linkimport:'.$user->id.':'.$token))->pluck('status')->all())->toBe(['ok', 'ok', 'quota']);
    $this->actingAs($user)->post('/links/import/confirm', ['token' => $token]);
    expect(Link::where('user_id', $user->id)->count())->toBe(3);
});

test('a slug taken between the preview and the confirm skips that row only', function () {
    $user = User::factory()->create();
    [$token] = importPreview($this, $user, "destination,slug\nhttps://example.com/r1,race-one\nhttps://example.com/r2,race-two\n");
    Link::create(['user_id' => User::factory()->create()->id, 'slug' => 'race-one', 'target_url' => 'https://example.org/x', 'status' => 'active']);
    $this->actingAs($user)->post('/links/import/confirm', ['token' => $token]);
    expect(Link::where('user_id', $user->id)->pluck('slug')->all())->toBe(['race-two']);
    $this->actingAs($user)->get(route('links.import'))->assertSee('Skipped: the slug is already taken.');
});

test('another account cannot open or confirm someone’s preview', function () {
    $alice = User::factory()->create();
    [$token] = importPreview($this, $alice, "destination\nhttps://example.com/secret\n");
    $bob = User::factory()->create();
    $this->actingAs($bob)->get(route('links.import', ['preview' => $token]))->assertRedirect(route('links.import'));
    $this->actingAs($bob)->post('/links/import/confirm', ['token' => $token])->assertSessionHasErrors('file');
    expect(Link::count())->toBe(0);
});

test('imported links reach webhooks like any other new link', function () {
    Http::fake();
    app()->bind(WebhookGuard::class, fn () => new class extends WebhookGuard
    {
        protected function resolveHost(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
    $user = User::factory()->create();
    $user->webhookEndpoints()->create(['url' => 'https://hooks.example.net/in', 'secret' => 'whsec_x', 'events' => ['link.created']]);
    [$token] = importPreview($this, $user, "destination\nhttps://example.com/w1\nhttps://example.com/w2\n");
    $this->actingAs($user)->post('/links/import/confirm', ['token' => $token]);
    $sent = collect(Http::recorded())->filter(fn ($p) => $p[0]->hasHeader('X-LinkHop-Event'))->count();
    expect($sent)->toBe(2);
});
