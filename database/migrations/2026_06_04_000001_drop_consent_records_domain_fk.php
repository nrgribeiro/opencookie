<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * US-LOG-4 / US-SET-4 — consent logs must survive domain/user deletion until
 * the 24-month retention boundary. The original sqlite/mysql fallback added a
 * cascading FK on domain_id; matching the postgres design, we drop the FK and
 * keep domain_id nullable so orphaned logs persist until purged by retention.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            return;
        }

        if (! Schema::hasTable('consent_records')) {
            return;
        }

        Schema::table('consent_records', function (Blueprint $table): void {
            try {
                $table->dropForeign(['domain_id']);
            } catch (Throwable) {
                // FK already absent (older snapshot) — safe to ignore.
            }
            $table->unsignedBigInteger('domain_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            return;
        }

        if (! Schema::hasTable('consent_records')) {
            return;
        }

        Schema::table('consent_records', function (Blueprint $table): void {
            $table->unsignedBigInteger('domain_id')->nullable(false)->change();
            $table->foreign('domain_id')->references('id')->on('domains')->cascadeOnDelete();
        });
    }
};
