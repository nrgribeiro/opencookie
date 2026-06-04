<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('banner_version');
            $table->string('language', 12);
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['domain_id', 'day', 'banner_version', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_impressions');
    }
};
