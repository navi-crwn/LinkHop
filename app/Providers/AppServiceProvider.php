<?php

namespace App\Providers;

use App\Bio\ShortlinkCreatorAdapter;
use App\Bio\UrlSafetyAdapter;
use App\Http\Controllers\BrandAssetController;
use App\Models\Link;
use App\Observers\LinkWebhookObserver;
use App\Services\GeoIpService;
use App\Services\LinkHopSafety;
use App\Services\LinkService;
use App\Services\UserAgentService;
use App\Services\UserDeletion;
use App\Support\EncryptedNoteLink;
use Hop\Auth\HopEvents;
use Hop\Bio\Contracts\GeoLookup;
use Hop\Bio\Contracts\ShortlinkCreator;
use Hop\Bio\Contracts\UrlSafety;
use Hop\Bio\Contracts\UserAgentParser;
use Hop\Safety\LinkSafety;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // What the hop/bio package may call in this app (Hop\Bio\Contracts); nothing else.
        // URL safety is the family's one LinkSafety (hop/safety), scoped per request; LinkHop adds its own rules on top.
        $this->app->scoped(EncryptedNoteLink::class);
        $this->app->scoped(LinkSafety::class, LinkHopSafety::class);
        $this->app->bind(ShortlinkCreator::class, ShortlinkCreatorAdapter::class);
        $this->app->bind(UrlSafety::class, UrlSafetyAdapter::class);
        $this->app->bind(GeoLookup::class, GeoIpService::class);
        $this->app->bind(UserAgentParser::class, UserAgentService::class);
    }

    public function boot(): void
    {
        URL::defaults(['v' => BrandAssetController::version()]); // brand image URLs change with the images
        \URL::forceRootUrl(config('app.url'));
        if (config('app.env') === 'production') {
            \URL::forceScheme('https');
        }
        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at(match ($proxies) {
                '*' => '*',
                'cloudflare' => [...config('app.cloudflare_ips'), '127.0.0.1', '::1'],
                default => array_map('trim', explode(',', $proxies)),
            });
        }
        Link::observe(LinkWebhookObserver::class);
        // Account events from the hub (POST /hop/events). hop/auth already ends the sessions and rotates the remember
        // token on session.revoked and account.deleted; these are what LinkHop adds.
        HopEvents::on('account.deleted', function (array $data): void {
            app(UserDeletion::class)->purgeById((int) ($data['sub'] ?? $data['hop_id'] ?? 0), (array) ($data['qr_codes'] ?? []), (array) ($data['bio_uploads'] ?? []));
        });
        // CheckSuspended already signs a suspended account out on its next request; the cached short links
        // still hold their owner as they were for five minutes, so they are dropped here.
        HopEvents::on('account.status', function (array $data): bool {
            $id = HopEvents::accountId($data);
            if ($id !== null) {
                Link::where('user_id', $id)->pluck('slug')->each(fn (string $slug) => app(LinkService::class)->forgetCacheBySlug($slug));
            }

            return $id !== null;
        });
        // Defined here (not in routes/web.php) so they exist when routes are cached.
        RateLimiter::for('shorten', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(30)->by('user:'.$request->user()->id)
                : Limit::perMinute(10)->by($request->ip());
        });
        // Flood guard only: bio click limits normally just stop counting (see PublicBioController::click).
        RateLimiter::for('bio-click', function (Request $request) {
            return Limit::perMinute(300)->by('bio-click:'.$request->ip());
        });
    }
}
