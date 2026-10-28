<?php

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use App\Support\VisitorHash;
use Hop\Bio\Models\BioClick;
use Illuminate\Support\Facades\DB;

function ownedLinkWithVisit(): array
{
    $owner = User::factory()->create();
    $link = Link::create(['user_id' => $owner->id, 'slug' => 'privacy1', 'target_url' => 'https://example.com', 'status' => 'active']);
    $click = LinkClick::create([
        'link_id' => $link->id,
        'ip_address' => '203.0.113.77',
        'country' => 'ID',
        'city' => 'Bandung',
        'isp' => 'PT Visitor Network',
        'clicked_at' => now(),
    ]);

    return [$owner, $link, $click];
}

test('click logs store a keyed hash, never the raw IP', function () {
    [, , $click] = ownedLinkWithVisit();
    $stored = DB::table('link_clicks')->where('id', $click->id)->value('ip_address');

    expect($stored)->not->toBe('203.0.113.77')
        ->and($stored)->toBe(VisitorHash::make('203.0.113.77'))
        ->and(strlen($stored))->toBeLessThanOrEqual(45)
        ->and($click->toArray())->not->toHaveKey('ip_address');

    // Re-saving a loaded row does not hash the hash again.
    $click->refresh()->save();
    expect(DB::table('link_clicks')->where('id', $click->id)->value('ip_address'))->toBe($stored);
});

test('bio click logs store a keyed hash too', function () {
    $click = new BioClick(['ip_address' => '2001:db8::1']);

    expect($click->getAttributes()['ip_address'])->toBe(VisitorHash::make('2001:db8::1'))
        ->and($click->getAttributes()['ip_address'])->not->toContain(':');
});

test('link owners never see a visitor IP, city or network per visit', function () {
    [$owner, $link] = ownedLinkWithVisit();

    foreach ([route('analytics'), route('links.show', $link)] as $url) {
        $this->actingAs($owner)->get($url)->assertOk()
            ->assertDontSee('203.0.113.77')
            ->assertDontSee(VisitorHash::make('203.0.113.77'));
    }
    $this->actingAs($owner)->get(route('links.show', $link))
        ->assertDontSee('"isp"', false)
        ->assertDontSee('Bandung","isp', false);
});

test('the migration hashes IPs that were stored raw', function () {
    [, $link] = ownedLinkWithVisit();
    $id = DB::table('link_clicks')->insertGetId(['link_id' => $link->id, 'ip_address' => '198.51.100.9', 'created_at' => now(), 'updated_at' => now()]);

    (require database_path('migrations/2026_10_15_000002_hash_click_ip_addresses.php'))->up();

    expect(DB::table('link_clicks')->where('id', $id)->value('ip_address'))->toBe(VisitorHash::make('198.51.100.9'))
        ->and(DB::table('link_clicks')->where('ip_address', '203.0.113.77')->exists())->toBeFalse();
});
