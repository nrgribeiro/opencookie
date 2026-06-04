<?php

namespace App\Jobs;

use App\Enums\CookieStatus;
use App\Enums\ScanStatus;
use App\Http\Controllers\Ingest\DeclarationController;
use App\Models\CookieOverride;
use App\Models\Scan;
use App\Services\Scanner\CookieClassifier;
use App\Services\Scanner\DetectedCookie;
use App\Services\Scanner\SiteScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class RunScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Free-tier crawl cap (functional-spec §8). */
    public const PAGE_LIMIT = 100;

    public function __construct(public readonly int $scanId) {}

    public function handle(SiteScanner $scanner, CookieClassifier $classifier): void
    {
        $scan = Scan::with('domain')->findOrFail($this->scanId);
        $domain = $scan->domain;

        $scan->update([
            'status' => ScanStatus::Running,
            'started_at' => now(),
            'error' => null,
        ]);

        try {
            $result = $scanner->scan($domain, self::PAGE_LIMIT);

            $overrides = $domain->cookieOverrides()->get()
                ->keyBy(fn (CookieOverride $o) => $o->cookie_name.'|'.($o->source_domain ?? ''));

            $seen = [];

            foreach ($result->cookies as $detected) {
                /** @var DetectedCookie $detected */
                $key = $detected->name.'|'.($detected->sourceDomain ?? '');
                $seen[$key] = true;

                $override = $overrides->get($key);
                $category = $override?->category ?? $classifier->classify($detected->name, $detected->sourceDomain);

                $existing = $domain->cookies()
                    ->where('name', $detected->name)
                    ->where('source_domain', $detected->sourceDomain)
                    ->first();

                $domain->cookies()->updateOrCreate(
                    ['name' => $detected->name, 'source_domain' => $detected->sourceDomain],
                    [
                        'scan_id' => $scan->id,
                        'provider' => $override?->provider ?? $detected->provider,
                        'category' => $category,
                        'purpose' => $override?->purpose,
                        'expiry' => $detected->expiry,
                        'type' => $detected->type,
                        'is_first_party' => $detected->isFirstParty,
                        'status' => $existing ? CookieStatus::Active : CookieStatus::New,
                        'first_seen_at' => $existing?->first_seen_at ?? now(),
                        'last_seen_at' => now(),
                    ],
                );
            }

            // US-SCAN-4 — mark cookies not seen in this scan (cookies upserted above
            // are in $seen, so they are skipped).
            $domain->cookies()
                ->where('status', '!=', CookieStatus::NotSeen->value)
                ->get()
                ->each(function ($cookie) use ($seen): void {
                    $key = $cookie->name.'|'.($cookie->source_domain ?? '');
                    if (! isset($seen[$key])) {
                        $cookie->update(['status' => CookieStatus::NotSeen]);
                    }
                });

            $scan->update([
                'status' => ScanStatus::Complete,
                'pages_crawled' => $result->pagesCrawled,
                'finished_at' => now(),
            ]);

            $domain->update(['last_scanned_at' => now()]);

            // Refresh the public cookie declaration (US-DECL-1 / US-DECL-3).
            DeclarationController::bustCache($domain);

            // US-SCAN-4 / US-SET-3 — alert owner if new or unclassified cookies appeared.
            SendCookieAlertJob::dispatch($scan->id);
        } catch (Throwable $e) {
            $scan->update([
                'status' => ScanStatus::Failed,
                'finished_at' => now(),
                'error' => Str::limit($e->getMessage(), 480),
            ]);

            throw $e;
        }
    }
}
