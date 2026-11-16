<?php

use App\Models\AbuseReport;
use App\Models\Link;
use App\Support\GuestClaim;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| abuse_reports.source: which app a report is about
|--------------------------------------------------------------------------
| The table is shared with BioHop. Reports about bio pages carry source = 'biohop' and stay out of LinkHop's queue
| (the queue itself is tested in HopContentApiTest).
*/

function dropAbuseReportSource(): void
{
    Schema::table('abuse_reports', function ($table) {
        $table->dropIndex(['source']);
        $table->dropColumn('source');
    });
}

test('the column is there, indexed, and a report made without a source is a LinkHop one', function () {
    expect(Schema::hasColumn('abuse_reports', 'source'))->toBeTrue()->and(Schema::hasIndex('abuse_reports', ['source']))->toBeTrue();
    $r = AbuseReport::create(['slug' => 'x', 'url' => 'https://hel.ink/x', 'reason' => 'spam spam spam', 'status' => 'open']);
    expect($r->fresh()->source)->toBe('linkhop');
});

test('the migration fills old reports about bio pages in, leaves the rest and deletes nothing', function () {
    $migration = require base_path('database/migrations/2026_11_16_000001_add_source_to_abuse_reports_table.php');
    dropAbuseReportSource();
    $row = fn (string $url) => DB::table('abuse_reports')->insertGetId(['url' => $url, 'reason' => 'spam spam spam', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
    $bio = $row('https://hel.ink/b/some-page');
    $short = $row('https://hel.ink/abc123');
    $none = DB::table('abuse_reports')->insertGetId(['url' => null, 'reason' => 'spam spam spam', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);

    $migration->up();

    expect(Schema::hasColumn('abuse_reports', 'source'))->toBeTrue()->and(Schema::hasIndex('abuse_reports', ['source']))->toBeTrue()
        ->and(DB::table('abuse_reports')->count())->toBe(3)
        ->and(DB::table('abuse_reports')->where('id', $bio)->value('source'))->toBe('biohop')
        ->and(DB::table('abuse_reports')->where('id', $short)->value('source'))->toBe('linkhop')
        ->and(DB::table('abuse_reports')->where('id', $none)->value('source'))->toBe('linkhop');
});

test('running the migration again, or on a table that already has the column, changes nothing', function () {
    $migration = require base_path('database/migrations/2026_11_16_000001_add_source_to_abuse_reports_table.php');
    $r = AbuseReport::create(['slug' => 'x', 'url' => 'https://hel.ink/b/x', 'reason' => 'spam spam spam', 'status' => 'open', 'source' => 'linkhop']);

    $migration->up();
    $migration->up();

    // an admin's or BioHop's value is not re-guessed from the address on a second run
    expect($r->fresh()->source)->toBe('linkhop')->and(AbuseReport::count())->toBe(1);
});

test('the migration can be taken back and run again', function () {
    $migration = require base_path('database/migrations/2026_11_16_000001_add_source_to_abuse_reports_table.php');
    $migration->down();
    $migration->down();
    expect(Schema::hasColumn('abuse_reports', 'source'))->toBeFalse();
    $migration->up();
    expect(Schema::hasColumn('abuse_reports', 'source'))->toBeTrue();
});

test('the scope keeps LinkHop reports and drops the BioHop ones, and does nothing before the column exists', function () {
    AbuseReport::create(['slug' => 'a', 'reason' => 'spam spam spam', 'status' => 'open']);
    AbuseReport::create(['slug' => 'b', 'reason' => 'spam spam spam', 'status' => 'open', 'source' => 'biohop']);
    expect(AbuseReport::aboutLinks()->pluck('slug')->all())->toBe(['a']);

    dropAbuseReportSource();
    expect(AbuseReport::aboutLinks()->count())->toBe(2);
});

test('a bio page report with the same slug does not stop a guest link from being claimed', function () {
    $token = str_repeat('k', 48);
    $mk = fn (string $slug) => Link::create(['slug' => $slug, 'target_url' => 'https://example.com/x', 'status' => 'active', 'claim_token_hash' => GuestClaim::hash($token)]);
    $bioOnly = $mk('bioonly');
    $linkReported = $mk('linkrep');
    AbuseReport::create(['slug' => 'bioonly', 'url' => 'https://hel.ink/b/bioonly', 'reason' => 'spam spam spam', 'status' => 'open', 'source' => 'biohop']);
    AbuseReport::create(['slug' => 'linkrep', 'url' => 'https://hel.ink/linkrep', 'reason' => 'spam spam spam', 'status' => 'open']);

    expect(GuestClaim::claimable($token)->pluck('id')->all())->toBe([$bioOnly->id])->not->toContain($linkReported->id);

    dropAbuseReportSource(); // before the migration, every report counts as before
    expect(GuestClaim::claimable($token)->count())->toBe(0);
});
