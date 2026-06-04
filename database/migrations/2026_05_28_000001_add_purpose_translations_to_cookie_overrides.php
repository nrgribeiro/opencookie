<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-DECL-3 — per-language purpose strings on manual cookie overrides.
 * Stored as { "<lang>": "<text>" }. The plain `purpose` column remains the
 * default-language fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cookie_overrides', function (Blueprint $table): void {
            $table->json('purpose_translations')->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('cookie_overrides', function (Blueprint $table): void {
            $table->dropColumn('purpose_translations');
        });
    }
};
