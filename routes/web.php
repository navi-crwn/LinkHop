<?php

use App\Http\Controllers\AbuseReportController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\BrandAssetController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\EditorToolsController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\GuestClaimController;
use App\Http\Controllers\Internal\AccountDataController;
use App\Http\Controllers\LegacySlugController;
use App\Http\Controllers\LinkCheckController;
use App\Http\Controllers\LinkCommentController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\LinkImportController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicLinkController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\QrStudioController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\NoIndex;
use App\Support\CspReport;
use Hop\Auth\Http\Controllers\EventController;
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
    Route::get('/changelog', ChangelogController::class)->middleware('hop.recheck')->name('changelog');
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
    // hop.recheck: these public pages show the signed-in account, so a stale session is confirmed with the hub (silent,
    // once per hop-auth.recheck.max_age). Never on /l/{slug} or the other redirect hot paths.
    Route::get('/', [PublicLinkController::class, 'index'])->middleware('hop.recheck')->name('home');
    Route::post('/shorten', [PublicLinkController::class, 'store'])
        ->middleware(['throttle:shorten', 'quota'])
        ->name('shorten.store');
    // Sign in / out through the hub. `login` keeps its name so the `auth` middleware and old links still work.
    Route::get('/login', [SessionController::class, 'login'])->name('login');
    Route::get('/register', fn () => redirect()->away(hub_url('register')))->name('register');
    Route::match(['get', 'post'], '/logout', [SessionController::class, 'logout'])->name('logout');
    Route::get('/api/qr-generate', QrController::class)->name('api.qr.generate')->middleware('throttle:60,1,qr');
    // The guest QR page: Wi-Fi, contact card, link or text, drawn in the browser. GET only, nothing is sent or logged.
    Route::get('/qr', QrStudioController::class)->middleware('hop.recheck')->name('qr');
    // The notice about the move to /l/ is linked from emails and the dashboard: readable without signing in.
    Route::view('/changes/short-links', 'announcements.short-links')->name('announcements.short-links');
    Route::middleware(['hop.auth'])->group(function () {
        Route::get('/dashboard', [LinkController::class, 'index'])->name('dashboard');
        Route::get('/links', [LinkController::class, 'myLinks'])->name('links.index');
        Route::get('/analytics', AnalyticsController::class)->name('analytics');
        // Saved QR codes: the codes of the member's short links and the Wi-Fi, contact and text codes they keep.
        Route::get('/qr-codes', [QrCodeController::class, 'index'])->name('qr-codes.index');
        Route::middleware('throttle:20,1,qr-codes')->group(function () {
            Route::post('/qr-codes', [QrCodeController::class, 'store'])->name('qr-codes.store');
            Route::patch('/qr-codes/{id}', [QrCodeController::class, 'update'])->where('id', '[0-9A-Za-z]{26}')->name('qr-codes.update');
            Route::delete('/qr-codes/{id}', [QrCodeController::class, 'destroy'])->where('id', '[0-9A-Za-z]{26}')->name('qr-codes.destroy');
        });
        Route::get('links/create', [LinkController::class, 'create'])->name('links.create');
        // Guest links made in this browser before signing in (hop_claim cookie): one claim, rate limited.
        Route::post('links/claim', GuestClaimController::class)->middleware('throttle:6,1,link-claim')->name('links.claim');
        Route::post('links/fetch-og', [LinkController::class, 'fetchOgData'])->name('links.fetch-og');
        // CSV import and the editor helpers (slug check, command palette): above the links resource, whose {link} would catch them.
        Route::get('links/import', [LinkImportController::class, 'show'])->name('links.import');
        Route::get('links/import/sample', [LinkImportController::class, 'sample'])->name('links.import.sample');
        Route::post('links/import', [LinkImportController::class, 'preview'])->middleware('throttle:10,1,link-import')->name('links.import.preview');
        Route::post('links/import/confirm', [LinkImportController::class, 'commit'])->middleware('throttle:10,1,link-import')->name('links.import.commit');
        Route::get('links/slug-check', [EditorToolsController::class, 'slugCheck'])->middleware('throttle:90,1,slug-check')->name('links.slug-check');
        Route::get('palette/links', [EditorToolsController::class, 'links'])->middleware('throttle:90,1,palette')->name('palette.links');
        // The same lookup for the Analytics link picker, with its own limit so typing in both places cannot starve one of them.
        Route::get('analytics/links', [EditorToolsController::class, 'links'])->middleware('throttle:90,1,analytics-links')->name('analytics.links');
        Route::get('links/{link}/qr/{format}', [LinkController::class, 'downloadQr'])
            ->where('format', 'png|svg|jpg')
            ->name('links.qr.download');
        Route::post('links/{link}/qr', [LinkController::class, 'updateQr'])->name('links.qr.update');
        Route::resource('links', LinkController::class)->except(['index']);
        Route::post('links/{link}/comments', [LinkCommentController::class, 'store'])->name('links.comments.store');
        // Profile, password, 2FA and API tokens are the hub's pages now.
        Route::get('/profile', fn () => redirect()->away(hub_url('profile')))->name('profile.edit');
        Route::post('/reminders/dismiss', [ReminderController::class, 'dismiss'])->middleware('throttle:30,1,reminders')->name('reminders.dismiss');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::post('/settings/monitoring', [SettingsController::class, 'updateMonitoring'])->name('settings.monitoring');
        Route::post('/settings/export', [SettingsController::class, 'exportData'])->name('settings.export');
        Route::post('/settings/export-analytics', [SettingsController::class, 'exportAnalytics'])->name('settings.export.analytics');
        Route::middleware('throttle:30,1,webhooks')->prefix('/settings/webhooks')->name('webhooks.')->group(function () {
            Route::post('/', [WebhookController::class, 'store'])->name('store');
            Route::patch('/{endpoint}', [WebhookController::class, 'update'])->whereNumber('endpoint')->name('update');
            Route::delete('/{endpoint}', [WebhookController::class, 'destroy'])->whereNumber('endpoint')->name('destroy');
            Route::post('/{endpoint}/test', [WebhookController::class, 'test'])->whereNumber('endpoint')->name('test');
            Route::post('/{endpoint}/rotate', [WebhookController::class, 'rotate'])->whereNumber('endpoint')->name('rotate');
        });
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
    // Dedicated pages on LinkHop's own domain (U-07); menus link here, not to the hub.
    foreach (PageController::PAGES as $page) {
        Route::get('/'.$page, [PageController::class, 'show'])->defaults('page', $page)->middleware('hop.recheck')->name('pages.'.$page);
    }
    Route::get('/status', [PageController::class, 'status'])->middleware('hop.recheck')->name('pages.status');
    Route::get('/check', LinkCheckController::class)->middleware('throttle:30,1,link-check')->name('link.check');
    Route::get('/report-abuse', [AbuseReportController::class, 'create'])->name('report.create');
    Route::post('/report-abuse', [AbuseReportController::class, 'store'])->middleware('throttle:5,10,report-abuse')->name('report.store');
});
// What this app holds for an account, for the hub (export on request, purge when
// the account is deleted). Signed server-to-server with the shared SSO secret (VerifyHubRequest).
Route::middleware(['hub.signed', 'throttle:60,1,internal'])->prefix('internal')->name('internal.')->group(function () {
    Route::get('/accounts/{user}/export', [AccountDataController::class, 'export'])->name('accounts.export');
    Route::get('/accounts/{user}/summary', [AccountDataController::class, 'summary'])->whereNumber('user')->name('accounts.summary');
    Route::delete('/accounts/{user}', [AccountDataController::class, 'purge'])->whereNumber('user')->name('accounts.purge');
});
// Account events from the hub (session.revoked, account.status, account.deleted): hop/auth's controller behind this app's own signature check; handlers in AppServiceProvider.
Route::post('/hop/events', EventController::class)
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
// Root redirect fallback for hel.ink/l/ and /l when routed here (U-05).
Route::match(['GET', 'HEAD'], '/l', function (Request $request) {
    return redirect()->away(rtrim((string) config('app.url'), '/').'/'.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
});
Route::match(['GET', 'HEAD'], '/l/', function (Request $request) {
    return redirect()->away(rtrim((string) config('app.url'), '/').'/'.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
});
// Health check for the watchdog: a plain answer (the framework's default /up page loads a CDN script and font).
Route::get('/up', fn () => response('ok', 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']));

// Short links: hel.ink/l/{slug}. GET opens the link, POST submits a link password.
// NoIndex: no answer of a short link (redirect, preview, password, ended, missing) is ever a search result.
Route::middleware(['ip.ban', NoIndex::class])->match(['GET', 'POST'], '/l/{slug}', RedirectController::class)
    ->where('slug', '[A-Za-z0-9._-]+')->name('links.open');
// The old form hel.ink/{slug}: 301 to /l/{slug} until shortener.legacy_slug_until (LegacySlugController).
// Registered last, so every app route above wins; a path that is not a slug is a 404.
Route::middleware(['ip.ban', NoIndex::class])->get('/{slug}', LegacySlugController::class)
    ->where('slug', '[A-Za-z0-9-]+')->name('links.legacy');
