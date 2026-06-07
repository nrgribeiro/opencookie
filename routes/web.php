<?php

use App\Http\Controllers\BannerController;
use App\Http\Controllers\ConsentLogController;
use App\Http\Controllers\CookieController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DomainSettingsController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Setup & compliance guide (replaces the starter-kit docs link).
    Route::inertia('guide', 'guide')->name('guide');

    Route::get('domains', [DomainController::class, 'index'])->name('domains.index');
    Route::get('domains/create', [DomainController::class, 'create'])->name('domains.create');
    Route::post('domains', [DomainController::class, 'store'])->name('domains.store');
    Route::get('domains/{domain}', [DomainController::class, 'show'])->name('domains.show');
    Route::post('domains/{domain}/verify', [DomainController::class, 'verify'])->name('domains.verify');
    Route::delete('domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');

    Route::post('domains/{domain}/scans', [ScanController::class, 'store'])->name('scans.store');
    Route::patch('cookies/{cookie}', [CookieController::class, 'update'])->name('cookies.update');

    Route::get('domains/{domain}/banner', [BannerController::class, 'edit'])->name('banner.edit');
    Route::put('domains/{domain}/banner', [BannerController::class, 'update'])->name('banner.update');
    Route::post('domains/{domain}/banner/publish', [BannerController::class, 'publish'])->name('banner.publish');

    // US-LOG-3 / US-DASH-4 — consent log preview + CSV export.
    Route::get('domains/{domain}/consent-logs', [ConsentLogController::class, 'index'])->name('consent-logs.index');
    Route::get('domains/{domain}/consent-logs/export', [ConsentLogController::class, 'export'])->name('consent-logs.export');

    // US-SET-1/2/3 — per-domain settings (consent expiry, policy version, notifications).
    Route::get('domains/{domain}/settings', [DomainSettingsController::class, 'edit'])->name('domain-settings.edit');
    Route::put('domains/{domain}/settings', [DomainSettingsController::class, 'update'])->name('domain-settings.update');
    Route::post('domains/{domain}/policy-versions', [DomainSettingsController::class, 'bumpPolicy'])->name('policy-versions.store');
});

require __DIR__.'/settings.php';
