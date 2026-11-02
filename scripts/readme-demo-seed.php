<?php

/*
| Demo data for the README screenshots (scripts/readme-images.mjs). Run it against the throwaway
| database of tests/e2e/serve.sh, never against a real one:
|
|   APP_ENV=e2e php scripts/readme-demo-seed.php
|
| Everything here is invented: one demo account on the reserved example.test domain, links that
| point at example.com / example.org, and click rows with made-up countries, devices and referrers.
*/

use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('e2e')) {
    fwrite(STDERR, "Refusing to run outside APP_ENV=e2e.\n");
    exit(1);
}

mt_srand(20261102);
$pick = fn (array $items) => $items[mt_rand(0, count($items) - 1)];

$user = User::firstOrCreate(['email' => 'demo@example.test'], [
    'name' => 'Demo account',
    'password' => Hash::make(bin2hex(random_bytes(12))),
]);
$user->forceFill(['email_verified_at' => now(), 'status' => 'active', 'username' => 'demo'])->save();

$folders = [];
foreach (['Campaigns', 'Docs', 'Events'] as $name) {
    $folders[$name] = Folder::firstOrCreate(['user_id' => $user->id, 'name' => $name]);
}
$tags = [];
foreach (['newsletter', 'social', 'print', 'launch'] as $name) {
    $tags[$name] = Tag::firstOrCreate(['user_id' => $user->id, 'name' => $name]);
}

// slug, destination, folder, tags, weight (share of the clicks), extras
$links = [
    ['spring-sale', 'https://example.com/shop/spring-sale', 'Campaigns', ['newsletter', 'social'], 30, ['custom_title' => 'Spring sale']],
    ['launch-day', 'https://example.com/blog/launch-day', 'Campaigns', ['launch'], 22, []],
    ['menu-qr', 'https://example.org/menu', 'Events', ['print'], 16, []],
    ['docs', 'https://example.org/docs/getting-started', 'Docs', [], 12, []],
    ['team-photo', 'https://example.com/about/team', 'Docs', ['social'], 8, []],
    ['conf-2027', 'https://example.org/conference/2027', 'Events', ['print', 'launch'], 7, ['max_clicks' => 500]],
    ['promo', 'https://example.com/promo', 'Campaigns', ['newsletter'], 5, ['expires_at' => now()->addDays(12)]],
    ['members', 'https://example.org/members', 'Docs', [], 3, ['password_hash' => Hash::make('demo-only')]],
];

$countries = [
    ['US', 'United States', ['New York', 'Chicago', 'Austin'], 24],
    ['DE', 'Germany', ['Berlin', 'Hamburg'], 12],
    ['ID', 'Indonesia', ['Jakarta', 'Surabaya'], 14],
    ['BR', 'Brazil', ['Sao Paulo', 'Recife'], 10],
    ['JP', 'Japan', ['Tokyo', 'Osaka'], 9],
    ['FR', 'France', ['Paris', 'Lyon'], 8],
    ['GB', 'United Kingdom', ['London', 'Leeds'], 8],
    ['ES', 'Spain', ['Madrid', 'Valencia'], 6],
    ['IN', 'India', ['Mumbai', 'Pune'], 5],
    ['CA', 'Canada', ['Toronto'], 4],
];
$agents = [
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1', 34],
    ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36', 24],
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15', 14],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', 20],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0', 8],
];
$referrers = [[null, 30], ['https://www.google.com/', 20], ['https://t.co/', 14], ['https://www.instagram.com/', 14], ['https://newsletter.example.com/', 12], ['https://www.reddit.com/', 6], ['https://www.linkedin.com/', 4]];
$weighted = function (array $rows, int $weightIndex) {
    $total = array_sum(array_column($rows, $weightIndex));
    $n = mt_rand(1, $total);
    foreach ($rows as $row) {
        $n -= $row[$weightIndex];
        if ($n <= 0) {
            return $row;
        }
    }

    return $rows[0];
};

DB::transaction(function () use ($user, $folders, $tags, $links, $countries, $agents, $referrers, $weighted, $pick) {
    $now = now();
    foreach ($links as $i => [$slug, $target, $folder, $tagNames, $weight, $extra]) {
        $link = Link::updateOrCreate(['slug' => $slug], [
            'user_id' => $user->id,
            'target_url' => $target,
            'folder_id' => $folders[$folder]->id,
            'status' => Link::STATUS_ACTIVE,
            'created_at' => $now->copy()->subDays(40 - $i * 3),
        ] + $extra);
        $link->tags()->sync(array_map(fn ($t) => $tags[$t]->id, $tagNames));
        $link->clicks()->delete();
        $total = $weight * 14;
        for ($c = 0; $c < $total; $c++) {
            // More traffic in the recent days, with a weekday rhythm.
            $daysAgo = (int) floor(29 * (mt_rand(0, 1000) / 1000) ** 1.6);
            $at = $now->copy()->subDays($daysAgo)->setTime(mt_rand(7, 22), mt_rand(0, 59), mt_rand(0, 59));
            if ($at->isWeekend() && mt_rand(0, 2) === 0) {
                continue;
            }
            $country = $weighted($countries, 3);
            $agent = $weighted($agents, 1);
            $ref = $weighted($referrers, 1);
            LinkClick::create([
                'link_id' => $link->id,
                'referer' => $ref[0],
                'ip_address' => '198.51.100.'.mt_rand(1, 250), // documentation range, stored as a keyed hash
                'visitor_hash' => bin2hex(random_bytes(8)),
                'source' => $slug === 'menu-qr' && mt_rand(0, 2) > 0 ? 'qr' : 'direct',
                'country' => $country[0],
                'country_name' => $country[1],
                'city' => $pick($country[2]),
                'user_agent' => $agent[0],
                'clicked_at' => $at,
            ]);
        }
        $link->forceFill(['clicks' => $link->clicks()->count(), 'last_clicked_at' => $link->clicks()->max('clicked_at')])->save();
    }
});

echo "Seeded demo@example.test with ".count($links)." links.\n";
