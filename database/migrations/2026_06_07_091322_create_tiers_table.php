<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('max_domains')->nullable();        // null = unlimited
            $table->unsignedInteger('max_scan_pages')->default(100);
            $table->unsignedBigInteger('monthly_pageview_cap')->nullable(); // null = unlimited
            $table->boolean('scheduled_scans_allowed')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tier_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tier_id');
        });

        Schema::dropIfExists('tiers');
    }
};
