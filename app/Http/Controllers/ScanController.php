<?php

namespace App\Http\Controllers;

use App\Enums\ScanStatus;
use App\Enums\ScanTrigger;
use App\Jobs\RunScanJob;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ScanController extends Controller
{
    /**
     * US-SCAN-1 — trigger an on-demand scan. Requires a verified domain.
     */
    public function store(Domain $domain): RedirectResponse
    {
        $this->authorize('update', $domain);

        if (! $domain->isVerified()) {
            throw ValidationException::withMessages([
                'scan' => 'Verify domain ownership before scanning.',
            ]);
        }

        $scan = $domain->scans()->create([
            'status' => ScanStatus::Queued,
            'trigger' => ScanTrigger::Manual,
        ]);

        RunScanJob::dispatch($scan->id);

        return back()->with('status', 'Scan started. Results appear when complete.');
    }
}
