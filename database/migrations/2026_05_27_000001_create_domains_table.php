<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('domain_uid')->unique();        // public "dom_..." id
            $table->string('hostname')->unique();
            $table->string('verify_status')->default('pending');
            $table->unsignedSmallInteger('consent_expiry_days')->default(365);
            $table->boolean('scheduled_scan_enabled')->default(false);
            $table->string('scan_frequency')->nullable();   // weekly|monthly
            $table->timestamp('last_scanned_at')->nullable();
            $table->boolean('banner_live')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
