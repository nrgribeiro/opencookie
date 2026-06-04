<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a provider_url column to cookies and cookie_overrides so the consent
 * banner's details modal can link out to the third-party provider's policy
 * (when the owner supplies one).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cookies', function (Blueprint $table): void {
            $table->string('provider_url', 2048)->nullable()->after('provider');
        });

        Schema::table('cookie_overrides', function (Blueprint $table): void {
            $table->string('provider_url', 2048)->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('cookies', function (Blueprint $table): void {
            $table->dropColumn('provider_url');
        });

        Schema::table('cookie_overrides', function (Blueprint $table): void {
            $table->dropColumn('provider_url');
        });
    }
};
