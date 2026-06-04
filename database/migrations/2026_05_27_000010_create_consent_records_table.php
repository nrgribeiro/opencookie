<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only, month-partitioned consent proof (data-model.md §2.10 / §3).
 * Postgres: native RANGE partitioning on created_at, PK (id, created_at).
 * SQLite (local dev): plain table fallback — no partitioning.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE consent_records (
                    id                BIGINT GENERATED ALWAYS AS IDENTITY,
                    created_at        TIMESTAMPTZ NOT NULL,
                    domain_id         BIGINT NOT NULL,
                    consent_id        UUID NOT NULL,
                    method            TEXT NOT NULL CHECK (method IN ('accept_all','reject_all','custom')),
                    categories        JSONB NOT NULL,
                    banner_version    INTEGER NOT NULL,
                    policy_version    INTEGER NOT NULL,
                    consent_text_hash TEXT NOT NULL,
                    ip_hash           TEXT,
                    user_agent        TEXT,
                    language          TEXT,
                    expires_at        TIMESTAMPTZ,
                    PRIMARY KEY (id, created_at)
                ) PARTITION BY RANGE (created_at);
            SQL);

            DB::statement('CREATE INDEX idx_consent_domain_created ON consent_records (domain_id, created_at)');
            DB::statement('CREATE INDEX idx_consent_consent_id ON consent_records (consent_id)');

            // Seed current + next month partitions so writes have a target.
            // Ongoing partitions are pre-created by a scheduled job.
            $this->createMonthlyPartition(now()->startOfMonth());
            $this->createMonthlyPartition(now()->startOfMonth()->addMonth());

            return;
        }

        // SQLite / other: plain table fallback for local dev.
        // No FK on domain_id — consent logs must survive domain/user deletion
        // until the retention boundary purges them (US-LOG-4 / US-SET-4).
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->uuid('consent_id');
            $table->string('method');
            $table->json('categories');
            $table->unsignedInteger('banner_version');
            $table->unsignedInteger('policy_version');
            $table->string('consent_text_hash');
            $table->string('ip_hash')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('language')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->index(['domain_id', 'created_at']);
            $table->index('consent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }

    private function createMonthlyPartition(Carbon $start): void
    {
        $from = $start->copy()->startOfMonth();
        $to = $from->copy()->addMonth();
        $name = 'consent_records_'.$from->format('Y_m');

        DB::statement(sprintf(
            "CREATE TABLE IF NOT EXISTS %s PARTITION OF consent_records FOR VALUES FROM ('%s') TO ('%s')",
            $name,
            $from->toDateString(),
            $to->toDateString(),
        ));
    }
};
