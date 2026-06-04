<?php

use App\Jobs\PurgeExpiredConsentJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// US-SCAN-5 — recurring scheduled scans.
Schedule::command('scans:dispatch-scheduled --frequency=weekly')->weekly();
Schedule::command('scans:dispatch-scheduled --frequency=monthly')->monthly();

// US-LOG-4 — daily 24-month retention purge.
Schedule::job(new PurgeExpiredConsentJob)->dailyAt('03:15');
