<?php

namespace App\Console\Commands;

use App\Enums\DomainVerifyStatus;
use App\Enums\ScanStatus;
use App\Enums\ScanTrigger;
use App\Jobs\RunScanJob;
use App\Models\Domain;
use Illuminate\Console\Command;

class DispatchScheduledScans extends Command
{
    protected $signature = 'scans:dispatch-scheduled {--frequency=monthly}';

    protected $description = 'Queue scheduled scans for verified domains with scheduled scanning enabled (US-SCAN-5).';

    public function handle(): int
    {
        $frequency = (string) $this->option('frequency');

        $domains = Domain::query()
            ->where('scheduled_scan_enabled', true)
            ->where('scan_frequency', $frequency)
            ->where('verify_status', DomainVerifyStatus::Verified->value)
            ->get();

        foreach ($domains as $domain) {
            $scan = $domain->scans()->create([
                'status' => ScanStatus::Queued,
                'trigger' => ScanTrigger::Scheduled,
            ]);

            RunScanJob::dispatch($scan->id);
        }

        $this->info("Dispatched {$domains->count()} scheduled scan(s) [{$frequency}].");

        return self::SUCCESS;
    }
}
