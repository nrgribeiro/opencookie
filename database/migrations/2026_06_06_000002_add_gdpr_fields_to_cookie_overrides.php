<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets owners manually override the OCD-sourced GDPR fields per cookie, mirroring
 * the existing provider/provider_url overrides. Overrides survive future scans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cookie_overrides', function (Blueprint $table): void {
            $table->string('retention')->nullable()->after('provider_url');
            $table->string('data_controller')->nullable()->after('retention');
            $table->string('gdpr_portal_url', 2048)->nullable()->after('data_controller');
        });
    }

    public function down(): void
    {
        Schema::table('cookie_overrides', function (Blueprint $table): void {
            $table->dropColumn(['retention', 'data_controller', 'gdpr_portal_url']);
        });
    }
};
