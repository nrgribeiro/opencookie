<?php

use App\Http\Controllers\Ingest\ConfigController;
use App\Http\Controllers\Ingest\ConsentController;
use App\Http\Controllers\Ingest\DeclarationController;
use App\Http\Controllers\Ingest\ImpressionController;
use Illuminate\Support\Facades\Route;

/*
 * Public Consent Ingest API (technical-spec §6.1). Prefixed with /v1/c and named
 * ingest.* by bootstrap/app.php. Stateless: no session, no CSRF.
 */
Route::middleware('throttle:ingest')->group(function () {
    Route::get('{domainUid}/config', ConfigController::class)->name('config');
    Route::post('{domainUid}/consent', ConsentController::class)->name('consent');
    Route::post('{domainUid}/impression', ImpressionController::class)->name('impression');
    Route::get('{domainUid}/declaration.js', DeclarationController::class)->name('declaration');
});
