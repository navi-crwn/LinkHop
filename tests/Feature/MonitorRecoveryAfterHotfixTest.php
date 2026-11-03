<?php

use App\Models\Link;
use App\Models\User;
use Hop\Safety\UrlGuard;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/*
| OP-02: links that the destination monitor flagged as broken because of the dual-stack pinning bug. After the fix, does
| the flag clear by itself, how long does it take, and does anyone hear about it?
*/

function mrGuard(array $ips = ['93.184.216.34']): void
{
    app()->instance(UrlGuard::class, new class($ips) extends UrlGuard
    {
        public function __construct(private array $ips) {}

        protected function resolveHost(string $host): array
        {
            return filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->ips;
        }
    });
}

function mrFlagged(array $when): Link
{
    $user = User::factory()->create(['monitor_destinations' => true]);
    $link = Link::create(['user_id' => $user->id, 'slug' => 'flagged'.Str::random(4), 'target_url' => 'https://dest.example/page', 'status' => 'active']);
    $link->forceFill(['check_failures' => 12, 'last_status' => null, 'broken_since' => now()->subDays(2)] + $when)->saveQuietly();

    return $link;
}

test('a link wrongly flagged during the bug clears by itself at the next check once the destination answers', function () {
    mrGuard();
    Mail::fake();
    Http::fake(['dest.example/*' => Http::response('', 200)]);
    $link = mrFlagged(['last_checked_at' => now()->subHours(25)]);

    $this->artisan('links:check-destinations')->assertSuccessful();

    $fresh = $link->fresh();
    expect($fresh->broken_since)->toBeNull()->and($fresh->check_failures)->toBe(0)->and($fresh->last_status)->toBe(200);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

test('but a flagged link is looked at again only after 24 hours, so a wrong flag can stay a day', function () {
    mrGuard();
    Http::fake(['dest.example/*' => Http::response('', 200)]);
    $link = mrFlagged(['last_checked_at' => now()->subHours(2)]);

    $this->artisan('links:check-destinations')->assertSuccessful();
    expect($link->fresh()->broken_since)->not->toBeNull();
    Http::assertNothingSent();

    $this->travel(21)->hours();
    $this->artisan('links:check-destinations')->assertSuccessful();
    expect($link->fresh()->broken_since)->not->toBeNull();

    $this->travel(2)->hours();
    $this->artisan('links:check-destinations')->assertSuccessful();
    expect($link->fresh()->broken_since)->toBeNull();
});

test('forcing the re-check right away: clearing last_checked_at is enough', function () {
    mrGuard();
    Http::fake(['dest.example/*' => Http::response('', 200)]);
    $link = mrFlagged(['last_checked_at' => now()->subMinutes(5)]);
    Link::whereKey($link->id)->update(['last_checked_at' => null]);

    $this->artisan('links:check-destinations')->assertSuccessful();

    expect($link->fresh()->broken_since)->toBeNull();
});

test('nobody is told that the flag was wrong: there is no recovery mail and no correction mail', function () {
    mrGuard();
    Mail::fake();
    Http::fake(['dest.example/*' => Http::response('', 200)]);
    mrFlagged(['last_checked_at' => now()->subHours(25)]);

    $this->artisan('links:check-destinations')->assertSuccessful();

    Mail::assertNothingQueued();
    expect(array_keys(Artisan::all()))->not->toContain('links:correct-monitor-notices');
});

test('edge: a flagged link whose host later counts as "cannot tell" (skipped) keeps its flag', function () {
    // the host now resolves to a private address, so the monitor will not knock on it (state skipped): nothing clears the flag
    mrGuard(['10.0.0.5']);
    Http::fake();
    $link = mrFlagged(['last_checked_at' => now()->subHours(25)]);

    $this->artisan('links:check-destinations')->assertSuccessful();

    expect($link->fresh()->broken_since)->not->toBeNull()->and($link->fresh()->last_checked_at->isToday())->toBeTrue();
});

test('the pin handed to curl lists every checked address, IPv6 and IPv4, so a client without IPv6 can use the IPv4 one', function () {
    $guard = new class extends UrlGuard
    {
        protected function resolveHost(string $host): array
        {
            return ['2606:2800:220:1:248:1893:25c8:1946', '93.184.216.34'];
        }
    };

    $target = $guard->resolve('https://dual.example/x');

    expect($guard->curlResolve($target))->toBe(['dual.example:443:[2606:2800:220:1:248:1893:25c8:1946],93.184.216.34']);
});
