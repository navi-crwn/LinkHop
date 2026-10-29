<?php

use App\Http\Controllers\AbuseReportController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\BrandAssetController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\Internal\AccountDataController;
use App\Http\Controllers\LegacySlugController;
use App\Http\Controllers\LinkCheckController;
use App\Http\Controllers\LinkCommentController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\PublicLinkController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TagController;
use App\Support\CspReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LinkHop: link.hel.ink (dashboard, API docs) + hel.ink/l/* (short links)
|--------------------------------------------------------------------------
| Accounts live at the hub (hub.hel.ink, HOP ID). /login sends a guest through
| the hub's SSO; hop/auth registers /auth/sso, /auth/sso/callback and
| /auth/sso/logout and signs the same user in here (shared users table).
| Public routes (/l/{slug}, the old /{slug}, /b/* while bio is on, sitemap, QR)
| build their URLs from PUBLIC_URL; everything else from APP_URL.
*/

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
    // link.hel.ink/: LinkHop's landing (hero animation, guest shortener) for guests, the dashboard once
    // signed in. The HOP family home page is the hub's (hel.ink).
    Route::get('/', [PublicLinkController::class, 'index'])->name('home');
    Route::post('/shorten', [PublicLinkController::class, 'store'])
        ->middleware(['throttle:shorten', 'quota'])
        ->name('shorten.store');
    // Sign in / out through the hub. `login` keeps its name so the `auth` middleware and old links still work.
    Route::get('/login', [SessionController::class, 'login'])->name('login');
    Route::get('/register', fn () => redirect()->away(hub_url('register')))->name('register');
    Route::match(['get', 'post'], '/logout', [SessionController::class, 'logout'])->name('logout');
    Route::get('/api/qr-generate', QrController::class)->name('api.qr.generate')->middleware('throttle:60,1,qr');
    Route::middleware(['hop.auth'])->group(function () {
        Route::get('/dashboard', [LinkController::class, 'index'])->name('dashboard');
        Route::view('/changes/short-links', 'announcements.short-links')->name('announcements.short-links');
        Route::get('/links', [LinkController::class, 'myLinks'])->name('links.index');
        Route::get('/analytics', AnalyticsController::class)->name('analytics');
        Route::get('links/create', [LinkController::class, 'create'])->name('links.create');
        Route::post('links/fetch-og', [LinkController::class, 'fetchOgData'])->name('links.fetch-og');
        Route::get('links/{link}/qr/{format}', [LinkController::class, 'downloadQr'])
            ->where('format', 'png|svg|jpg')
            ->name('links.qr.download');
        Route::post('links/{link}/qr/save', [LinkController::class, 'saveCustomQr'])->name('links.qr.save');
        Route::resource('links', LinkController::class)->except(['index']);
        Route::post('links/{link}/comments', [LinkCommentController::class, 'store'])->name('links.comments.store');
        // Profile, password, 2FA and API tokens are the hub's pages now.
        Route::get('/profile', fn () => redirect()->away(hub_url('profile')))->name('profile.edit');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::post('/settings/export', [SettingsController::class, 'exportData'])->name('settings.export');
        Route::post('/settings/export-analytics', [SettingsController::class, 'exportAnalytics'])->name('settings.export.analytics');
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
    Route::middleware(['hop.auth', 'admin'])->group(function () {
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
// What this app holds for an account, for the hub (export on request, purge when
// the account is deleted). Signed server-to-server with the shared SSO secret (VerifyHubRequest).
Route::middleware(['hub.signed', 'throttle:60,1,internal'])->prefix('internal')->name('internal.')->group(function () {
    Route::get('/accounts/{user}/export', [AccountDataController::class, 'export'])->name('accounts.export');
    Route::delete('/accounts/{user}', [AccountDataController::class, 'purge'])->whereNumber('user')->name('accounts.purge');
});
Route::post('/hop/events', [AccountDataController::class, 'events'])
    ->middleware(['hub.signed', 'throttle:60,1,internal'])
    ->name('hop.events');
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
