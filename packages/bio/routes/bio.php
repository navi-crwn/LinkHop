<?php

use Hop\Bio\Http\Controllers\BioLinkController;
use Hop\Bio\Http\Controllers\BioPageController;
use Hop\Bio\Http\Controllers\BioShortlinkController;
use Hop\Bio\Http\Controllers\PublicBioController;
use Illuminate\Support\Facades\Route;

// Public pages: hel.ink/b/{slug}. The host app's ip.ban middleware keeps banned visitors out.
Route::middleware(['web', 'ip.ban'])->prefix('b')->name('bio.public.')->group(function () {
    Route::match(['get', 'post'], '/{slug}', [PublicBioController::class, 'show'])->name('show');
    Route::get('/{slug}/qr', [PublicBioController::class, 'qr'])->name('qr');
    Route::get('/click/{bioLink:public_id}', [PublicBioController::class, 'click'])->middleware('throttle:bio-click')->name('click');
});

// The editor, for signed-in members (hop.auth: a guest is sent through the hub's SSO; the hub only signs in verified accounts).
Route::middleware(['web', 'ip.ban', 'hop.auth'])->prefix('bio')->name('bio.')->group(function () {
    Route::get('/', [BioPageController::class, 'index'])->name('index');
    Route::get('/create', [BioPageController::class, 'create'])->name('create');
    Route::get('/check-slug', [BioPageController::class, 'checkSlug'])->name('check-slug');
    Route::post('/', [BioPageController::class, 'store'])->name('store');
    Route::get('/{bioPage}/edit', [BioPageController::class, 'edit'])->name('edit');
    Route::put('/{bioPage}', [BioPageController::class, 'update'])->name('update');
    Route::delete('/{bioPage}', [BioPageController::class, 'destroy'])->name('destroy');
    Route::get('/{bioPage}/analytics', [BioPageController::class, 'analytics'])->name('analytics');
    Route::post('/{bioPage}/duplicate', [BioPageController::class, 'duplicate'])->name('duplicate');
    Route::post('/{bioPage}/upload-image', [BioPageController::class, 'uploadImage'])->name('upload-image');
    Route::post('/{bioPage}/upload-avatar', [BioPageController::class, 'uploadAvatar'])->name('upload-avatar');
    Route::post('/{bioPage}/upload-thumbnail', [BioPageController::class, 'uploadThumbnail'])->name('upload-thumbnail');
    Route::post('/{bioPage}/upload-icon', [BioPageController::class, 'uploadIcon'])->name('upload-icon');
    Route::post('/{bioPage}/upload-qr-logo', [BioPageController::class, 'uploadQrLogo'])->name('upload-qr-logo');
    Route::get('/{bioPage}/preview', [BioPageController::class, 'preview'])->name('preview');
    // Short links for blocks, through the ShortlinkCreator contract (same safety checks and quota as other links).
    Route::get('/links/search', [BioShortlinkController::class, 'search'])->name('links.search');
    Route::post('/shorten', [BioShortlinkController::class, 'store'])
        ->middleware(['throttle:shorten', 'quota'])
        ->name('shorten');
    // scopeBindings: {bioLink} must belong to {bioPage}, otherwise 404
    Route::prefix('{bioPage}/links')->name('links.')->scopeBindings()->group(function () {
        Route::post('/', [BioLinkController::class, 'store'])->name('store');
        Route::put('/{bioLink}', [BioLinkController::class, 'update'])->name('update');
        Route::delete('/{bioLink}', [BioLinkController::class, 'destroy'])->name('destroy');
        Route::post('/reorder', [BioLinkController::class, 'reorder'])->name('reorder');
        Route::post('/{bioLink}/toggle', [BioLinkController::class, 'toggle'])->name('toggle');
    });
});

// The same shortener door under /api for the editor's older JavaScript (web session, not an API token).
Route::middleware(['api', 'web', 'hop.auth', 'throttle:shorten', 'quota'])
    ->post('/api/bio/shorten', [BioShortlinkController::class, 'store'])->name('bio.api.shorten');
