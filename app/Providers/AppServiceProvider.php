<?php

namespace App\Providers;

use App\Bio\ShortlinkCreatorAdapter;
use App\Bio\UrlSafetyAdapter;
use App\Http\Controllers\BrandAssetController;
use App\Services\GeoIpService;
use App\Services\UserAgentService;
use Hop\Bio\Contracts\GeoLookup;
use Hop\Bio\Contracts\ShortlinkCreator;
use Hop\Bio\Contracts\UrlSafety;
use Hop\Bio\Contracts\UserAgentParser;
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
        // URL safety is the family's one LinkSafety (hop/safety), already scoped per request.
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
