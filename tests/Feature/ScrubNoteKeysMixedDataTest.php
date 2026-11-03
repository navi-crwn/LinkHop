<?php

use App\Models\ActivityLog;
use App\Models\Link;
use App\Models\User;
use Hop\Bio\Models\BioLink;
use Hop\Bio\Models\BioPage;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| links:scrub-note-keys on a database that looks like a real one: other fragment parameters, other hosts, escaped JSON,
| a host written full-width, bio blocks, sessions and cached copies, a failure half way, and a big table. Made-up keys.
*/

const SM_KEY = 'FAKEKEYzzz999';

function smOwner(): User
{
    return User::factory()->create();
}

function smLink(User $owner, string $slug, string $url, array $more = []): Link
{
    return Link::create($more + ['user_id' => $owner->id, 'slug' => $slug, 'target_url' => $url, 'status' => 'active']);
}

it('takes out only k and a and keeps the rest of the fragment', function () {
    $owner = smOwner();
    $a = smLink($owner, 'keepnew', 'https://note.hel.ink/Ab3dEf#new=1&k='.SM_KEY);
    $b = smLink($owner, 'keepsection', 'https://n.hel.ink/Ab3dEf#section&a='.SM_KEY.'&k='.SM_KEY);
    $c = smLink($owner, 'questionmark', 'https://note.hel.ink/Ab3dEf#?k='.SM_KEY);
    $d = smLink($owner, 'plainkey', 'https://note.hel.ink/Ab3dEf#k='.SM_KEY);

    Artisan::call('links:scrub-note-keys', ['--apply' => true]);

    expect($a->fresh()->target_url)->toBe('https://note.hel.ink/Ab3dEf#new=1')
        ->and($b->fresh()->target_url)->toBe('https://n.hel.ink/Ab3dEf#section')
        ->and($c->fresh()->target_url)->toBe('https://note.hel.ink/Ab3dEf')
        ->and($d->fresh()->target_url)->toBe('https://note.hel.ink/Ab3dEf');
});

it('finds a link that was saved with the host written full-width', function () {
    $owner = smOwner();
    $a = smLink($owner, 'fullwidth', "https://\u{FF4E}ote.hel.ink/Ab3dEf#k=".SM_KEY);
    $b = smLink($owner, 'percent', 'https://no%74e.hel.ink/Ab3dEf#k='.SM_KEY);

    $this->artisan('links:scrub-note-keys')->expectsOutputToContain('Links would be cleaned: 2')->assertSuccessful();
    Artisan::call('links:scrub-note-keys', ['--apply' => true]);

    expect(DB::table('links')->where('target_url', 'like', '%'.SM_KEY.'%')->count())->toBe(0)
        ->and($a->fresh()->status)->toBe('inactive');
});

it('leaves another site\'s fragment alone in the log copies, and cuts the key out of escaped JSON', function () {
    $owner = smOwner();
    foreach ([
        'https://example.com/p#a=1',
        'https://example.com/doc#k=notasecret',
        'https://note.hel.ink/faq#privacy',
        'Created link: x → https://note.hel.ink/Ab3dEf#new=1&k='.SM_KEY,
    ] as $i => $text) {
        ActivityLog::create(['user_id' => $owner->id, 'action' => 'created', 'model' => 'Link', 'model_id' => $i, 'description' => $text]);
    }
    $endpoint = DB::table('webhook_endpoints')->insertGetId(['user_id' => $owner->id, 'url' => 'https://hooks.example.com/in', 'secret' => 'x', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    foreach ([['https://note.hel.ink/Ab3dEf#k='.SM_KEY], ["https://\u{FF4E}ote.hel.ink/Ab3dEf#k=".SM_KEY], ['https://example.com/x#a=1']] as $i => [$url]) {
        DB::table('webhook_deliveries')->insert(['webhook_endpoint_id' => $endpoint, 'uuid' => (string) Str::uuid(), 'event' => 'link.created', 'status' => 'sent',
            'payload' => json_encode(['link' => ['target_url' => $url]]), 'created_at' => now(), 'updated_at' => now()]);
    }

    Artisan::call('links:scrub-note-keys', ['--apply' => true]);

    expect(ActivityLog::pluck('description')->all())->toContain('https://example.com/p#a=1', 'https://example.com/doc#k=notasecret', 'https://note.hel.ink/faq#privacy', 'Created link: x → https://note.hel.ink/Ab3dEf#new=1')
        ->and(DB::table('webhook_deliveries')->pluck('payload')->implode("\n"))->not->toContain(SM_KEY)->toContain('example.com\/x#a=1');
});

it('also cuts the key out of bio blocks', function () {
    if (! bio_enabled()) {
        $this->markTestSkipped('needs the bio package');
    }
    $owner = smOwner();
    $page = BioPage::create(['user_id' => $owner->id, 'slug' => 'scrubbio', 'title' => 'x', 'theme' => 'default', 'is_published' => true]);
    $block = BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'n', 'url' => 'https://note.hel.ink/Ab3dEf#k='.SM_KEY, 'is_active' => true, 'order' => 0]);
    $other = BioLink::create(['bio_page_id' => $page->id, 'type' => 'link', 'title' => 'o', 'url' => 'https://example.com/#a=1', 'is_active' => true, 'order' => 1]);

    $this->artisan('links:scrub-note-keys')->expectsOutputToContain('bio_links.url rows would be cleaned: 1')->assertSuccessful();
    Artisan::call('links:scrub-note-keys', ['--apply' => true]);

    expect($block->fresh()->url)->toBe('https://note.hel.ink/Ab3dEf')->and($other->fresh()->url)->toBe('https://example.com/#a=1');
})->group('bio');

it('cuts the key out of stored sessions (the old input of a refused form) and drops cached copies', function () {
    config(['cache.default' => 'database']);
    $session = serialize(['_token' => 'abc', '_old_input' => ['target_url' => 'https://note.hel.ink/Ab3dEf#k='.SM_KEY, 'slug' => 'mine'], 'other' => 'https://example.com/#a=1']);
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => null, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => base64_encode($session), 'last_activity' => time()]);
    DB::table('sessions')->insert(['id' => 's2', 'user_id' => null, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => base64_encode(serialize(['_token' => 'zzz'])), 'last_activity' => time()]);
    DB::table('cache')->insert([
        ['key' => 'linkhop-cache-linkimport:1:abc', 'value' => serialize([['destination' => 'https://note.hel.ink/Ab3dEf#k='.SM_KEY]]), 'expiration' => time() + 600],
        ['key' => 'linkhop-cache-slug:oldone', 'value' => base64_encode("O:15:\"App\\Models\\Link\":1:{s:6:\"\0*\0url\";s:".strlen('https://note.hel.ink/Ab3dEf#k='.SM_KEY).':"https://note.hel.ink/Ab3dEf#k='.SM_KEY.'";}'), 'expiration' => time() + 600],
        ['key' => 'linkhop-cache-slug:fine', 'value' => serialize('https://example.com/#a=1'), 'expiration' => time() + 600],
    ]);

    $this->artisan('links:scrub-note-keys')->expectsOutputToContain('sessions.payload rows would be cleaned: 1')->expectsOutputToContain('cache entries would be deleted: 2')->assertSuccessful();
    expect(base64_decode((string) DB::table('sessions')->where('id', 's1')->value('payload')))->toContain(SM_KEY);

    Artisan::call('links:scrub-note-keys', ['--apply' => true]);

    $after = unserialize((string) base64_decode((string) DB::table('sessions')->where('id', 's1')->value('payload')));
    expect($after['_old_input']['target_url'])->toBe('https://note.hel.ink/Ab3dEf')->and($after['_old_input']['slug'])->toBe('mine')->and($after['other'])->toBe('https://example.com/#a=1')
        ->and(DB::table('sessions')->where('id', 's2')->value('payload'))->toBe(base64_encode(serialize(['_token' => 'zzz'])))
        ->and(DB::table('cache')->pluck('key')->all())->toBe(['linkhop-cache-slug:fine']);
});

it('is atomic per link: a failure in the middle leaves that link untouched and the command can be run again', function () {
    if (DB::getDriverName() !== 'sqlite') {
        // On MySQL/MariaDB the migrations of the test setup commit the test's own transaction, so Laravel's idea of the
        // transaction level is wrong and a savepoint fails. Production has no outer transaction: end it here.
        $level = new ReflectionProperty(Connection::class, 'transactions');
        $level->setValue(DB::connection(), 0);
    }
    $owner = smOwner();
    $first = smLink($owner, 'atomicone', 'https://note.hel.ink/Ab3dEf#k='.SM_KEY);
    $second = smLink($owner, 'atomictwo', 'https://note.hel.ink/Zz9999#k='.SM_KEY);
    ActivityLog::creating(function (ActivityLog $log) use ($second) {
        if ((int) $log->model_id === (int) $second->id) {
            throw new RuntimeException('disk full');
        }
    });

    try {
        Artisan::call('links:scrub-note-keys', ['--apply' => true]);
    } catch (RuntimeException) {
        // the command may stop on the error; what matters is the state it leaves
    }

    expect($first->fresh()->target_url)->toBe('https://note.hel.ink/Ab3dEf')
        ->and($second->fresh()->target_url)->toBe('https://note.hel.ink/Zz9999#k='.SM_KEY)
        ->and($second->fresh()->status)->toBe('active');
    ActivityLog::flushEventListeners();
    Artisan::call('links:scrub-note-keys', ['--apply' => true]);
    expect($second->fresh()->target_url)->toBe('https://note.hel.ink/Zz9999')->and($second->fresh()->status)->toBe('inactive');
    if (DB::getDriverName() !== 'sqlite') {
        ActivityLog::query()->delete();
        Link::query()->delete();
    }
});

it('counts failed_jobs and jobs but never edits them', function () {
    $payload = json_encode(['displayName' => 'X', 'data' => ['url' => 'https://note.hel.ink/Ab3dEf#k='.SM_KEY]]);
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => $payload, 'exception' => 'boom', 'failed_at' => now()]);

    $this->artisan('links:scrub-note-keys', ['--apply' => true])->expectsOutputToContain('failed_jobs: 1 entries hold a key and were left alone')->assertSuccessful();

    expect(DB::table('failed_jobs')->value('payload'))->toBe($payload);
});

it('works through a big table in pieces and only touches the rows that hold a key', function () {
    $owner = smOwner();
    $rows = [];
    for ($i = 1; $i <= 20000; $i++) {
        $url = $i % 500 === 0 ? 'https://note.hel.ink/Ab'.$i.'#k='.SM_KEY : 'https://example.com/page/'.$i.($i % 7 === 0 ? '#a=1' : '');
        $rows[] = ['user_id' => $owner->id, 'action' => 'created', 'model' => 'Link', 'model_id' => $i, 'description' => 'Created link: s'.$i.' → '.$url, 'created_at' => now(), 'updated_at' => now()];
        if (count($rows) === 1000) {
            DB::table('activity_logs')->insert($rows);
            $rows = [];
        }
    }
    $other = DB::table('activity_logs')->where('description', 'like', '%#a=1%')->count();
    $start = microtime(true);
    $peakBefore = memory_get_peak_usage(true);

    $this->artisan('links:scrub-note-keys')->expectsOutputToContain('activity_logs.description rows would be cleaned: 40')->assertSuccessful();
    expect(DB::table('activity_logs')->where('description', 'like', '%'.SM_KEY.'%')->count())->toBe(40);
    Artisan::call('links:scrub-note-keys', ['--apply' => true]);

    expect(DB::table('activity_logs')->where('description', 'like', '%'.SM_KEY.'%')->count())->toBe(0)
        ->and(DB::table('activity_logs')->where('description', 'like', '%#a=1%')->count())->toBe($other)
        ->and(DB::table('activity_logs')->count())->toBe(20000)
        ->and(memory_get_peak_usage(true) - $peakBefore)->toBeLessThan(64 * 1024 * 1024)
        ->and(microtime(true) - $start)->toBeLessThan(60);
});

it('cuts the key out of the browser fallback of a stored Android intent', function () {
    $owner = smOwner();
    $inner = 'https://note.hel.ink/Ab3dEf#new=1&k='.SM_KEY;
    $intent = 'intent://open/item#Intent;scheme=myapp;package=com.example.app;S.browser_fallback_url='.rawurlencode($inner).';end';
    $link = smLink($owner, 'intentlink', 'https://example.com/ok', ['app_links' => ['mode' => 'custom', 'android' => $intent, 'ios' => '', 'ios_store' => '', 'android_store' => '']]);

    Artisan::call('links:scrub-note-keys', ['--apply' => true]);

    $after = $link->fresh();
    expect(json_encode($after->app_links))->not->toContain(SM_KEY)->and($after->app_links['android'])->toContain(rawurlencode('https://note.hel.ink/Ab3dEf#new=1'))->and($after->status)->toBe('inactive');
});
