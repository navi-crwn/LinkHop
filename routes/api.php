<?php

use App\Http\Controllers\Api\HopContentController;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\ShortenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes: link.hel.ink/api (proxied from hel.ink/api until 27 Nov 2026)
|--------------------------------------------------------------------------
| API tokens are made and revoked at the hub (hub.hel.ink/settings) and live in
| the shared api_tokens table; `hop.token:{scope}` from hop/auth validates them.
| A token without scopes (made before scopes existed) may do everything.
| Scopes used here: link:create, link:read, link:delete. Documented in README, "API".
*/
Route::post('/shorten', [ShortenController::class, 'store'])->middleware('hop.token:link:create');
Route::get('/links/{slug}', [ShortenController::class, 'show'])->where('slug', '[A-Za-z0-9-]+')->middleware('hop.token:link:read');
Route::delete('/links/{slug}', [ShortenController::class, 'destroy'])->where('slug', '[A-Za-z0-9-]+')->middleware('hop.token:link:delete');
// The dashboard's own JavaScript (web session, not an API token).
Route::middleware(['web', 'hop.auth'])->group(function () {
    Route::get('/links', [LinkController::class, 'index']);
    Route::post('/shorten-web', [LinkController::class, 'shorten'])->middleware(['throttle:shorten', 'quota']);
    // POST /api/bio/shorten is registered by the hop/bio package (packages/bio/routes/bio.php) when bio is on.
    // Utility to fetch title from URL
    Route::post('/utils/fetch-title', [LinkController::class, 'fetchTitle']);
});
// Content moderation for the HOP control center (Imperium): links, the abuse
// queue, IP bans, the domain blacklist and site settings (HOP App Protocol,
// signed requests from an app registered with the admin_client capability).
Route::middleware(['hop.signed', 'throttle:600,1,hop', 'hop.admin_client'])->prefix('hop')->group(function () {
    Route::get('/health', [HopContentController::class, 'health']);
    Route::get('/stats', [HopContentController::class, 'stats']);
    Route::get('/manifest', [HopContentController::class, 'manifest']);
    Route::get('/content', [HopContentController::class, 'index']);
    Route::get('/content/{type}/{id}', [HopContentController::class, 'show'])->where(['type' => 'link|bio', 'id' => '[0-9]+']);
    Route::post('/content/{type}/{id}/actions', [HopContentController::class, 'act'])->where(['type' => 'link|bio', 'id' => '[0-9]+']);
    Route::get('/reports', [HopContentController::class, 'reports']);
    Route::post('/reports/{kind}/{id}/actions', [HopContentController::class, 'reportAct'])->where(['kind' => 'report|flag', 'id' => '[0-9]+']);
    Route::get('/x/ip', [HopContentController::class, 'ip']);
    Route::post('/x/ip/actions', [HopContentController::class, 'ipAct']);
    Route::get('/x/domains', [HopContentController::class, 'domains']);
    Route::post('/x/domains/actions', [HopContentController::class, 'domainsAct']);
    Route::get('/x/queue', [HopContentController::class, 'queue']);
    Route::get('/x/analytics', [HopContentController::class, 'analytics']);
    Route::get('/settings', [HopContentController::class, 'settings']);
    Route::put('/settings', [HopContentController::class, 'saveSettings']);
});
