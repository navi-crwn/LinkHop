<?php

use App\Models\Link;
use Illuminate\Support\Facades\DB;

/*
| links.safety_status holds what the guest policy and abuse:scan write ('pending', 'safe', 'flagged', NULL), and the
| migration 2026_11_11_000001 moves the values of the old enum column (production) to those.
*/

function ssMigration(): object
{
    return require database_path('migrations/2026_11_11_000001_widen_links_safety_status.php');
}

function ssStored(string $slug): ?string
{
    return DB::table('links')->where('slug', $slug)->value('safety_status');
}

beforeEach(function () {
    config(['shortener.guest_policy.kit_hosts' => ['fnjphdp.com']]);
});

it('stores pending for a guest link to an unknown site and safe once the scan clears it', function () {
    $this->post('/shorten', ['target_url' => 'https://my-little-shop.test/sale', 'cf-turnstile-response' => 'x'])
        ->assertSessionHasNoErrors();
    $slug = Link::sole()->slug;
    expect(ssStored($slug))->toBe(Link::SAFETY_PENDING);

    $this->artisan('abuse:scan', ['--since' => '2h'])->assertSuccessful();
    expect(ssStored($slug))->toBe(Link::SAFETY_SAFE);
});

it('stores NULL for a guest link to an allowlisted site, and the column defaults to NULL', function () {
    $this->post('/shorten', ['target_url' => 'https://github.com/navi-crwn/LinkHop', 'cf-turnstile-response' => 'x'])
        ->assertSessionHasNoErrors();
    expect(ssStored(Link::sole()->slug))->toBeNull();

    DB::table('links')->insert(['slug' => 'nodflt', 'target_url' => 'https://example.com/', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    expect(ssStored('nodflt'))->toBeNull();
});

it('moves the legacy enum values to the ones the code reads, and never to safe from suspicious or blocked', function () {
    $rows = ['unchecked' => 'leg01', 'clean' => 'leg02', 'suspicious' => 'leg03', 'blocked' => 'leg04', 'flagged' => 'leg05', 'pending' => 'leg06', 'safe' => 'leg07'];
    foreach ($rows as $value => $slug) {
        DB::table('links')->insert(['slug' => $slug, 'target_url' => 'https://example.com/'.$slug, 'status' => 'active', 'safety_status' => $value, 'created_at' => now(), 'updated_at' => now()]);
    }
    DB::table('links')->insert(['slug' => 'leg08', 'target_url' => 'https://example.com/leg08', 'status' => 'active', 'safety_status' => null, 'created_at' => now(), 'updated_at' => now()]);

    $migration = ssMigration();
    $migration->up();

    expect(ssStored('leg01'))->toBeNull()
        ->and(ssStored('leg02'))->toBe(Link::SAFETY_SAFE)
        ->and(ssStored('leg03'))->toBe(Link::SAFETY_PENDING)
        ->and(ssStored('leg04'))->toBe(Link::SAFETY_FLAGGED)
        ->and(ssStored('leg05'))->toBe(Link::SAFETY_FLAGGED)
        ->and(ssStored('leg06'))->toBe(Link::SAFETY_PENDING)
        ->and(ssStored('leg07'))->toBe(Link::SAFETY_SAFE)
        ->and(ssStored('leg08'))->toBeNull()
        ->and(array_keys(array_filter($migration::LEGACY, fn ($to) => $to === Link::SAFETY_SAFE)))->toBe(['clean']);

    // a moved suspicious link is behind the warning page again until the scan has looked at it
    $this->get('/l/leg03')->assertOk()->assertSee('Unverified destination');

    // running it again changes nothing, and down() leaves the data alone
    $migration->up();
    $migration->down();
    expect(DB::table('links')->whereIn('safety_status', array_keys($migration::LEGACY))->count())->toBe(0)
        ->and(ssStored('leg03'))->toBe(Link::SAFETY_PENDING)
        ->and(ssStored('leg02'))->toBe(Link::SAFETY_SAFE);
});
