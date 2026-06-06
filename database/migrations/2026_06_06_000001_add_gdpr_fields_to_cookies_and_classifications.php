<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surfaces three more Open Cookie Database fields in the consent banner's
 * cookie-details modal (functional-spec §8):
 *   - data_controller    — "Data Controller" column in OCD
 *   - gdpr_portal_url     — "User Privacy & GDPR Rights Portals" column
 *   - retention           — documented retention period (cookie_classifications
 *                           already has this; cookies needs it for display)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cookie_classifications', function (Blueprint $table): void {
            $table->string('data_controller')->nullable()->after('provider_url');
            $table->string('gdpr_portal_url', 2048)->nullable()->after('data_controller');
        });

        Schema::table('cookies', function (Blueprint $table): void {
            $table->string('retention')->nullable()->after('provider_url');
            $table->string('data_controller')->nullable()->after('retention');
            $table->string('gdpr_portal_url', 2048)->nullable()->after('data_controller');
        });
    }

    public function down(): void
    {
        Schema::table('cookie_classifications', function (Blueprint $table): void {
            $table->dropColumn(['data_controller', 'gdpr_portal_url']);
        });

        Schema::table('cookies', function (Blueprint $table): void {
            $table->dropColumn(['retention', 'data_controller', 'gdpr_portal_url']);
        });
    }
};
