<?php

namespace App\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * US-LOG-4 — enforce 24-month consent retention.
 *
 * pgsql: drops every monthly partition whose upper bound is fully before the
 * cutoff (O(1) per partition vs row deletes).
 * sqlite/other: deletes rows older than the cutoff.
 *
 * Action is auditable: counts + cutoff logged. Purged content is not retained.
 */
class PurgeExpiredConsentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const RETENTION_MONTHS = 24;

    public function handle(): void
    {
        $cutoff = CarbonImmutable::now()->subMonths(self::RETENTION_MONTHS)->startOfMonth();

        if (DB::getDriverName() === 'pgsql') {
            $this->purgePostgresPartitions($cutoff);

            return;
        }

        $deleted = DB::table('consent_records')
            ->where('created_at', '<', $cutoff)
            ->delete();

        Log::info('consent.retention.purge', [
            'driver' => DB::getDriverName(),
            'cutoff' => $cutoff->toIso8601String(),
            'rows_deleted' => $deleted,
        ]);
    }

    private function purgePostgresPartitions(CarbonImmutable $cutoff): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT child.relname AS name,
                   pg_get_expr(child.relpartbound, child.oid) AS bound
            FROM pg_inherits
            JOIN pg_class parent ON parent.oid = pg_inherits.inhparent
            JOIN pg_class child  ON child.oid  = pg_inherits.inhrelid
            WHERE parent.relname = 'consent_records'
        SQL);

        $dropped = [];

        foreach ($rows as $row) {
            $upper = $this->parsePartitionUpperBound((string) $row->bound);
            if ($upper === null) {
                continue;
            }

            // Drop only if the partition's upper bound is at/before the cutoff —
            // i.e. it contains no row newer than the retention boundary.
            if ($upper->lessThanOrEqualTo($cutoff)) {
                DB::statement('DROP TABLE IF EXISTS '.$row->name);
                $dropped[] = $row->name;
            }
        }

        Log::info('consent.retention.purge', [
            'driver' => 'pgsql',
            'cutoff' => $cutoff->toIso8601String(),
            'partitions_dropped' => $dropped,
        ]);
    }

    /**
     * Extract the upper bound from a `FOR VALUES FROM ('a') TO ('b')` expression.
     */
    private function parsePartitionUpperBound(string $bound): ?CarbonImmutable
    {
        if (! preg_match("/TO \\('([^']+)'\\)/", $bound, $m)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($m[1]);
        } catch (\Throwable) {
            return null;
        }
    }
}
