<?php

use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\PixelHopAccountController;
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
// PixelHop's own sign-in/sign-up forms use HEL.ink accounts (signed server-to-server calls).
// Every call comes from PixelHop's server, so this limit is shared by all its
// visitors; per-visitor limits live in the controller.
Route::middleware('throttle:300,1,pixelhop-account')->group(function () {
    Route::post('/pixelhop/login', [PixelHopAccountController::class, 'login']);
    Route::post('/pixelhop/register', [PixelHopAccountController::class, 'register']);
});
