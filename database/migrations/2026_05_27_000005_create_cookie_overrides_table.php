<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookie_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('cookie_name');
            $table->string('source_domain')->nullable();
            $table->string('category');               // necessary|preferences|statistics|marketing
            $table->string('provider')->nullable();
            $table->text('purpose')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'cookie_name', 'source_domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_overrides');
    }
};
