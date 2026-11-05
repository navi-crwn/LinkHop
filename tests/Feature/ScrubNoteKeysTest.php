<?php

use App\Models\ActivityLog;
use App\Models\Link;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
| links:scrub-note-keys, for links saved before LinkHop refused encrypted NoteHop addresses. The keys here are made up.
*/

const SK_URL = 'https://note.hel.ink/AbC123#k=secretkey';

/** @return array<string, mixed> the rows the command has to handle, and the ones it has to leave alone */
function skSeed(): array
{
    $owner = User::factory()->create();
    $make = fn (string $slug, array $attrs = []) => Link::create($attrs + ['user_id' => $owner->id, 'slug' => $slug, 'target_url' => 'https://example.com/'.$slug, 'status' => 'active']);

    $seed = [
        'owner' => $owner,
        'live' => $make('liveone', ['target_url' => SK_URL]),
        'short' => $make('shorty', ['target_url' => 'https://n.hel.ink/ZzZ999#k=secretkey&a=admintoken']),
        'others' => $make('others', ['target_url' => 'https://example.com/p', 'fallback_url' => SK_URL,
            'routing_rules' => [['url' => 'https://example.com/ok#section', 'country' => ['ID']], ['url' => SK_URL, 'country' => ['DE']]],
            'ab_variants' => [['url' => 'https://example.com/a', 'weight' => 50], ['url' => 'https://n.hel.ink/AbC123#k=secretkey', 'weight' => 50]]]),
        'guest' => $make('guestone', ['user_id' => null, 'target_url' => SK_URL]),
        'off' => $make('alreadyoff', ['target_url' => SK_URL, 'status' => 'inactive']),
        // untouched
        'plain' => $make('plainnote', ['target_url' => 'https://note.hel.ink/AbC123']),
        'anchor' => $make('anchorone', ['target_url' => 'https://note.hel.ink/faq#privacy']),
        'foreign' => $make('foreignhost', ['target_url' => 'https://example.com/doc#k=notasecret']),
    ];
    ActivityLog::create(['user_id' => $owner->id, 'action' => 'created', 'model' => 'Link', 'model_id' => 1, 'description' => 'Created link: liveone → '.SK_URL]);
    ActivityLog::create(['user_id' => $owner->id, 'action' => 'created', 'model' => 'Link', 'model_id' => 2, 'description' => 'Created link: plainnote → https://note.hel.ink/AbC123']);
    $endpoint = WebhookEndpoint::create(['user_id' => $owner->id, 'url' => 'https://hooks.example.com/in', 'secret' => 'whsec_x', 'is_active' => true]);
    DB::table('webhook_deliveries')->insert(['webhook_endpoint_id' => $endpoint->id, 'uuid' => (string) Str::uuid(), 'event' => 'link.created', 'status' => 'sent',
        'payload' => json_encode(['link' => ['target_url' => SK_URL, 'slug' => 'liveone']]), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('hop_audit')->insert(['actor' => 'system', 'app_slug' => 'imperium', 'action' => 'delete', 'target_type' => 'link', 'target_id' => '1', 'params' => json_encode(['slug' => 'x', 'target_url' => SK_URL])]);
    DB::table('api_idempotency_keys')->insert(['user_id' => $owner->id, 'key' => 'k1', 'fingerprint' => str_repeat('a', 64), 'status_code' => 201,
        'response_body' => json_encode(['data' => ['target_url' => SK_URL]]), 'expires_at' => now()->addHour(), 'created_at' => now()]);
    DB::table('abuse_reports')->insert(['slug' => 'x', 'url' => SK_URL, 'reason' => 'test', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => json_encode(['displayName' => 'X', 'data' => ['url' => SK_URL]]), 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

    return $seed;
}

function skEverything(): string
{
    return collect(['links', 'activity_logs', 'webhook_deliveries', 'hop_audit', 'api_idempotency_keys', 'abuse_reports'])
        ->map(fn ($t) => json_encode(DB::table($t)->get()))->implode("\n");
}

it('lists the links in a dry run and changes nothing', function () {
    $seed = skSeed();
    $before = skEverything();

    $this->artisan('links:scrub-note-keys')
        ->expectsOutputToContain('Dry run')
        ->expectsOutputToContain('Links would be cleaned: 5 (ids: '.implode(', ', [$seed['live']->id, $seed['short']->id, $seed['others']->id, $seed['guest']->id, $seed['off']->id]).')')
        ->expectsOutputToContain('activity_logs.description rows would be cleaned: 1')
        ->expectsOutputToContain('webhook_deliveries.payload rows would be cleaned: 1')
        ->expectsOutputToContain('hop_audit.params rows would be cleaned: 1')
        ->expectsOutputToContain('api_idempotency_keys.response_body rows would be cleaned: 1')
        ->expectsOutputToContain('abuse_reports.url rows would be cleaned: 1')
        ->expectsOutputToContain('jobs: 1 entries hold a key and were left alone')
        ->assertSuccessful();

    expect(skEverything())->toBe($before)->and($seed['live']->fresh()->status)->toBe('active');
});

it('never prints an address or a key', function () {
    skSeed();
    foreach ([[], ['--apply' => true]] as $options) {
        Artisan::call('links:scrub-note-keys', $options);
        $out = Artisan::output();
        expect($out)->not->toContain('secretkey')->not->toContain('admintoken')->not->toContain('note.hel.ink')->not->toContain('http');
    }
});

it('with --apply cuts the keys out everywhere, disables the links and tells the owner why in the activity log', function () {
    Mail::fake();
    Notification::fake();
    $seed = skSeed();
    // the redirect had been cached before such rows stopped being cached
    Cache::put('slug:liveone', Link::where('slug', 'liveone')->first(), 300);
    $deliveries = WebhookDelivery::count();
    Queue::fake();

    $this->artisan('links:scrub-note-keys', ['--apply' => true])
        ->expectsOutputToContain('Links cleaned: 5')
        ->expectsOutputToContain('Links switched off now: 4')
        ->assertSuccessful();

    expect(skEverything())->not->toContain('secretkey')->not->toContain('admintoken');
    foreach (['live', 'short', 'others', 'guest', 'off'] as $name) {
        expect($seed[$name]->fresh()->status)->toBe('inactive');
    }
    expect($seed['live']->fresh()->target_url)->toBe('https://note.hel.ink/AbC123')
        ->and($seed['short']->fresh()->target_url)->toBe('https://n.hel.ink/ZzZ999')
        ->and($seed['others']->fresh()->fallback_url)->toBe('https://note.hel.ink/AbC123')
        ->and($seed['others']->fresh()->routing_rules[0]['url'])->toBe('https://example.com/ok#section')
        ->and($seed['others']->fresh()->routing_rules[1]['url'])->toBe('https://note.hel.ink/AbC123')
        ->and($seed['others']->fresh()->ab_variants[1]['url'])->toBe('https://n.hel.ink/AbC123');

    // untouched: no key, another fragment, another host
    expect($seed['plain']->fresh()->status)->toBe('active')->and($seed['anchor']->fresh()->target_url)->toBe('https://note.hel.ink/faq#privacy')
        ->and($seed['foreign']->fresh()->target_url)->toBe('https://example.com/doc#k=notasecret')->and($seed['foreign']->fresh()->status)->toBe('active');
    expect(ActivityLog::where('description', 'Created link: plainnote → https://note.hel.ink/AbC123')->count())->toBe(1)
        ->and(ActivityLog::where('description', 'Created link: liveone → https://note.hel.ink/AbC123')->count())->toBe(1);

    // the reason is in the owner's log, one line per link that has an owner
    $reasons = ActivityLog::where('user_id', $seed['owner']->id)->where('action', 'disabled')->get();
    expect($reasons)->toHaveCount(4)->and($reasons->first()->description)->toContain('encrypted NoteHop note')->toContain('switched off');
    expect(DB::table('hop_audit')->value('params'))->toBeJson()->and(json_decode(DB::table('webhook_deliveries')->value('payload'), true)['link']['slug'])->toBe('liveone');

    // the cached redirect is gone, and nobody was told: no mail, no notification, no webhook, no job
    expect(Cache::has('slug:liveone'))->toBeFalse();
    $this->get('/l/liveone')->assertNotFound();
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
    Queue::assertNothingPushed();
    expect(WebhookDelivery::count())->toBe($deliveries);
    // the queue tables are only counted
    expect(DB::table('jobs')->value('payload'))->toContain('secretkey');
});

it('is safe to run twice', function () {
    skSeed();
    $this->artisan('links:scrub-note-keys', ['--apply' => true])->assertSuccessful();
    $after = skEverything();

    $this->artisan('links:scrub-note-keys', ['--apply' => true])->expectsOutputToContain('Links cleaned: 0')->assertSuccessful();

    expect(skEverything())->toBe($after);
});

it('has nothing to do on a clean database', function () {
    Link::create(['slug' => 'fine', 'target_url' => 'https://example.com', 'status' => 'active']);

    $this->artisan('links:scrub-note-keys')->expectsOutputToContain('Links would be cleaned: 0')->assertSuccessful();
});
