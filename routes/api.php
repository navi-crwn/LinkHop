<?php

use App\Http\Controllers\Api\AppAccountController;
use App\Http\Controllers\Api\HopAdminController;
use App\Http\Controllers\Api\HopContentController;
use App\Http\Controllers\Api\HopController;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\ShortenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded by the RouteServiceProvider within a group
| which is assigned the "api" middleware group.
|
*/
Route::middleware(['api.auth'])->group(function () {
    Route::post('/shorten', [ShortenController::class, 'store']);
});
// Shortlink creation and user library search - supports both Sanctum (API token) and web session auth
Route::middleware(['web', 'auth', '2fa'])->group(function () {
    Route::get('/links', [LinkController::class, 'index']);
    Route::post('/shorten-web', [LinkController::class, 'shorten'])->middleware(['verified', 'throttle:shorten', 'quota']);
    // Bio-specific shortlink creation (same safety checks and quota as other links)
    Route::post('/bio/shorten', [LinkController::class, 'shortenForBio'])->middleware(['verified', 'throttle:shorten', 'quota']);
    // Utility to fetch title from URL
    Route::post('/utils/fetch-title', [LinkController::class, 'fetchTitle']);
});
// The apps' own sign-in/sign-up forms use HOP ID accounts (signed server-to-server calls).
// Every call comes from the app's server, so this limit is shared by all its
// visitors; per-visitor limits live in the controller.
Route::middleware('throttle:300,1,app-account')->group(function () {
    // PixelHop 3.x, older signature headers.
    Route::post('/pixelhop/login', [AppAccountController::class, 'login'])->defaults('app', 'pixelhop');
    Route::post('/pixelhop/register', [AppAccountController::class, 'register'])->defaults('app', 'pixelhop');
    // HOP App Protocol.
    Route::middleware('hop.signed')->prefix('apps/{app}')->where(['app' => '[a-z0-9-]{1,32}'])->group(function () {
        Route::post('/login', [AppAccountController::class, 'login']);
        Route::post('/register', [AppAccountController::class, 'register']);
    });
});
// HOP ID for the apps and Imperium: profiles, the app registry and the admin API (HOP App Protocol).
Route::middleware(['hop.signed', 'throttle:600,1,hop'])->prefix('hop')->group(function () {
    Route::get('/profile/{user}', [HopController::class, 'profile']);
    Route::get('/profiles', [HopController::class, 'profiles']);
    Route::get('/apps', [HopController::class, 'apps']);
    Route::get('/health', [HopAdminController::class, 'health']);
    // The admin API: only for an app registered with the admin_client capability (Imperium).
    Route::middleware('hop.admin_client')->group(function () {
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
        Route::get('/settings', [HopContentController::class, 'settings']);
        Route::put('/settings', [HopContentController::class, 'saveSettings']);
        Route::get('/stats', [HopAdminController::class, 'stats']);
        Route::get('/users', [HopAdminController::class, 'users']);
        $missing = fn () => response()->json(['ok' => false, 'error' => 'not_found', 'message' => 'No such user.'], 404);
        Route::get('/users/{user}', [HopAdminController::class, 'user'])->missing($missing);
        Route::post('/users/{user}/actions', [HopAdminController::class, 'act'])->missing($missing);
        Route::get('/audit', [HopAdminController::class, 'audit']);
        Route::get('/search', [HopAdminController::class, 'search']);
    });
});
