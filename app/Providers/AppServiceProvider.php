<?php

namespace App\Providers;

use App\Http\Controllers\AppSsoController;
use App\Http\Controllers\BrandAssetController;
use App\Models\SeoSetting;
use App\Services\LinkSafety;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request/job so the domain blacklist is loaded once.
        $this->app->scoped(LinkSafety::class);
    }

    public function boot(): void
    {
        URL::defaults(['v' => BrandAssetController::version()]); // brand image URLs change with the images
        \URL::forceRootUrl(config('app.url'));
        // The password-reset mail speaks HOP ID: one password for every HOP app.
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false));
            $apps = collect(AppSsoController::apps())->pluck('name')->implode(', ');

            return (new MailMessage)
                ->subject('Reset your HOP ID password')
                ->greeting('Hi '.($notifiable->name ?: 'there').',')
                ->line('Someone asked to reset the password of your HOP ID ('.$notifiable->getEmailForPasswordReset().'). That is the one account for '.config('app.name').($apps ? ', '.$apps : '').' and every HOP app.')
                ->action('Choose a new password', $url)
                ->line('The link works for '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes and only for this address.')
                ->line('If you did not ask for this, you can ignore this email: your password stays as it is.');
        });
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
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour((int) config('abuse.register_per_ip_per_hour', 3))->by($request->ip());
        });
        View::composer('components.marketing-layout', function ($view) {
            $seo = cache()->rememberForever('seo.settings', fn () => SeoSetting::first());
            $view->with('seoSettings', $seo);
        });
    }
}
