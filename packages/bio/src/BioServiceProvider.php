<?php

namespace Hop\Bio;

use Hop\Bio\Models\BioPage;
use Hop\Bio\Policies\BioPagePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Link in bio as a package. Everything it registers (routes, views, migrations,
 * the policy) is skipped when bio.enabled (BIO_ENABLED) is false; the classes
 * stay loadable so the host app can keep type hints and guard with bio_enabled().
 *
 * The host app binds the four contracts in Hop\Bio\Contracts: that is the only
 * way the package reaches the shortener, URL safety, GeoIP and user-agent parsing.
 */
class BioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bio.php', 'bio');
        $this->mergeConfigFrom(__DIR__.'/../config/bio-themes.php', 'bio-themes');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/bio.php' => config_path('bio.php')], 'bio-config');
        if (! config('bio.enabled')) {
            return;
        }
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'bio');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/bio.php');
        Gate::policy(BioPage::class, BioPagePolicy::class);
    }
}
