<?php

use App\Http\Controllers\AbuseReportController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\AppSsoController;
use App\Http\Controllers\BrandAssetController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\LegacySlugController;
use App\Http\Controllers\LinkCheckController;
use App\Http\Controllers\LinkCommentController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\MarketingPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicLinkController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TwoFactorController;
use App\Support\CspReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::middleware('ip.ban')->group(function () {
    Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
    // Brand images, all from resources/brand (BrandAssetController). route() links carry a version ({v},
    // see AppServiceProvider) that changes with the files; the plain paths stay for emails and old pages.
    $brandFile = '[a-z0-9-]+\.(png|svg|ico)';
    Route::get('/brand/{v}/logo.png', [BrandAssetController::class, 'show'])->defaults('file', 'logo.png')->where('v', '[a-f0-9]{8}')->name('brand.logo');
    Route::get('/brand/{v}/logo-dark.png', [BrandAssetController::class, 'show'])->defaults('file', 'logo-dark.png')->where('v', '[a-f0-9]{8}')->name('brand.logo.dark');
    Route::get('/brand/{v}/favicon.png', [BrandAssetController::class, 'show'])->defaults('file', 'favicon.png')->where('v', '[a-f0-9]{8}')->name('brand.favicon');
    Route::get('/brand/{v}/{file}', [BrandAssetController::class, 'show'])->where(['v' => '[a-f0-9]{8}', 'file' => $brandFile])->name('brand.asset');
    Route::get('/brand/{file}', [BrandAssetController::class, 'legacy'])->where('file', $brandFile);
    Route::get('/', [PublicLinkController::class, 'index'])->name('home');
    Route::get('/onboarding', function () {
        return view('auth.onboarding');
    })->name('onboarding');
    Route::post('/onboarding/skip', function () {
        return redirect()->route('dashboard');
    })->name('onboarding.skip');
    Route::post('/onboarding/set-password', [ProfileController::class, 'createPasswordFromOnboarding'])->name('onboarding.set-password');
    Route::get('/goodbye', function () {
        return view('goodbye');
    })->name('goodbye');
    Route::get('/about', [MarketingPageController::class, 'about'])->name('about');
    Route::get('/contact', [MarketingPageController::class, 'contact'])->name('contact');
    Route::get('/contact-us', [MarketingPageController::class, 'contact'])->name('marketing.contact');
    Route::get('/privacy', [MarketingPageController::class, 'privacy'])->name('privacy');
    Route::get('/terms', [MarketingPageController::class, 'terms'])->name('terms');
    Route::get('/tos', function () {
        return view('tos');
    })->name('tos');
    Route::get('/plans', [MarketingPageController::class, 'plans'])->name('plans');
    Route::get('/help', [MarketingPageController::class, 'help'])->name('help');
    Route::get('/faq', [MarketingPageController::class, 'faq'])->name('faq');
    Route::get('/products', [MarketingPageController::class, 'products'])->name('products');
    Route::get('/feature-requests', [MarketingPageController::class, 'featureRequests'])->name('feature.requests');
    Route::get('/api/qr-generate', QrController::class)->name('api.qr.generate')->middleware('throttle:60,1,qr');
    Route::get('/changes/short-links', [MarketingPageController::class, 'shortLinkMove'])->name('announcements.short-links');
    Route::post('/shorten', [PublicLinkController::class, 'store'])
        ->middleware(['throttle:shorten', 'quota'])
        ->name('shorten.store');
    // "Continue with Google" in the apps: works for guests too (see AppSsoController::viaGoogle).
    Route::get('/sso/{app}/google', [AppSsoController::class, 'viaGoogle'])->where('app', '[a-z0-9-]{1,32}')->middleware('throttle:30,1,sso-google')->name('sso.google');
    Route::middleware(['auth'])->group(function () {
        Route::get('/two-factor-challenge', [TwoFactorController::class, 'show'])->name('two-factor.show');
        Route::post('/two-factor-challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:5,1,two-factor')->name('two-factor.verify');
    });
    Route::middleware(['auth', '2fa'])->group(function () {
        // The HOP apps (rows in `apps`: PixelHop, NoteHop, …) sign in here with HOP ID.
        Route::get('/sso/{app}', [AppSsoController::class, 'ticket'])->where('app', '[a-z0-9-]{1,32}')->middleware('throttle:30,1,sso')->name('sso.ticket');
        // Menu items open an app signed in. Not /images: public/images/ is a real directory.
        Route::get('/apps/{app}', [AppSsoController::class, 'open'])->where('app', '[a-z0-9-]{1,32}')->name('apps.open');
        Route::get('/pixelhop', fn (Request $r) => redirect()->route('apps.open', ['app' => 'pixelhop'] + $r->query()));
        Route::get('/notehop', fn (Request $r) => redirect()->route('apps.open', ['app' => 'notehop'] + $r->query()));
        Route::get('/dashboard', [LinkController::class, 'index'])->name('dashboard');
        Route::get('/links', [LinkController::class, 'myLinks'])->name('links.index');
        Route::get('/analytics', AnalyticsController::class)->name('analytics');
        Route::get('links/create', [LinkController::class, 'create'])->name('links.create');
        Route::post('links/fetch-og', [LinkController::class, 'fetchOgData'])->name('links.fetch-og');
        Route::get('links/{link}/qr/{format}', [LinkController::class, 'downloadQr'])
            ->where('format', 'png|svg|jpg')
            ->name('links.qr.download');
        Route::post('links/{link}/qr/save', [LinkController::class, 'saveCustomQr'])->name('links.qr.save');
        Route::resource('links', LinkController::class)->except(['index'])
            ->middlewareFor(['store', 'update'], 'verified');
        Route::post('links/{link}/comments', [LinkCommentController::class, 'store'])->name('links.comments.store');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/username-check', [ProfileController::class, 'usernameCheck'])->middleware('throttle:60,1,username-check')->name('profile.username-check');
        Route::post('/profile/avatar', [ProfileController::class, 'storeAvatar'])->name('profile.avatar');
        Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::post('/settings/export', [SettingsController::class, 'exportData'])->name('settings.export');
        Route::post('/settings/export-account', [SettingsController::class, 'exportAccount'])->middleware('throttle:5,1,export-account')->name('settings.export.account');
        Route::post('/settings/export-analytics', [SettingsController::class, 'exportAnalytics'])->name('settings.export.analytics');
        Route::post('/settings/2fa/enable', [TwoFactorController::class, 'enable'])->name('settings.2fa.enable');
        Route::post('/settings/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('settings.2fa.confirm');
        Route::delete('/settings/2fa/disable', [TwoFactorController::class, 'disable'])->name('settings.2fa.disable');
        Route::post('/settings/2fa/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('settings.2fa.recovery-codes');
        Route::post('/api-tokens', [ApiTokenController::class, 'store'])->middleware('verified')->name('api-tokens.store');
        Route::delete('/api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
        Route::get('/api-docs', [ApiTokenController::class, 'docs'])->name('api-docs');
        Route::post('/api/sharex-config', [ApiTokenController::class, 'sharexConfig'])->name('api.sharex-config');
        Route::get('/folders', [FolderController::class, 'index'])->name('folders.index');
        Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
        Route::get('/folders/{folder}/manage', [FolderController::class, 'manage'])->name('folders.manage');
        Route::put('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
        Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
        Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
        Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
        Route::get('/tags/{tag}/manage', [TagController::class, 'manage'])->name('tags.manage');
        Route::put('/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
    });
    // The admin pages moved to the HOP control center (Imperium), which works
    // through the signed /api/hop/* API. /admin/* sends admins there.
    Route::middleware(['auth', '2fa', 'admin'])->group(function () {
        $control = function () {
            if ($url = config('hop.control_url')) {
                return redirect()->away($url);
            }

            return view('admin-retired');
        };
        Route::get('/admin', $control)->name('admin.control');
        Route::get('/admin/{any}', $control)->where('any', '.*');
    });
    // Pages left open past the session lifetime fetch a new token before submitting (resources/js/app.js).
    Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store, private'))
        ->middleware('throttle:30,1,csrf-token')->name('csrf.token');
    Route::get('/check', LinkCheckController::class)->middleware('throttle:30,1,link-check')->name('link.check');
    Route::get('/report-abuse', [AbuseReportController::class, 'create'])->name('report.create');
    Route::post('/report-abuse', [AbuseReportController::class, 'store'])->middleware('throttle:5,10,report-abuse')->name('report.store');
});
// CSP violation reports (report-only policy). Logged to storage/logs/csp-*.log.
Route::post('/csp-report', function (Request $request) {
    $report = json_decode($request->getContent(), true)['csp-report'] ?? [];
    Log::build(['driver' => 'daily', 'path' => storage_path('logs/csp.log'), 'days' => 14])
        ->warning('csp', CspReport::sanitize(is_array($report) ? $report : []));

    return response()->noContent();
})->middleware('throttle:60,1,csp-report')->name('csp.report');
// Must stay above the /{slug} catch-all below.
Route::get('/health/queue', function () {
    $heartbeat = cache('queue:heartbeat');

    return response()->json([
        'status' => $heartbeat ? 'ok' : 'stale',
        'last_heartbeat' => $heartbeat,
    ]);
});
require __DIR__.'/auth.php';
if (env('E2E_HOOKS') === true && ! app()->isProduction()) {
    require __DIR__.'/e2e.php';
}
// Short links: hel.ink/l/{slug}. GET opens the link, POST submits a link password.
Route::middleware('ip.ban')->match(['GET', 'POST'], '/l/{slug}', RedirectController::class)
    ->where('slug', '[A-Za-z0-9-]+')->name('links.open');
// The old form hel.ink/{slug}: 301 to /l/{slug} until shortener.legacy_slug_until (LegacySlugController).
// Registered last, so every app route above wins; a path that is not a slug is a 404.
Route::middleware('ip.ban')->get('/{slug}', LegacySlugController::class)
    ->where('slug', '[A-Za-z0-9-]+')->name('links.legacy');
