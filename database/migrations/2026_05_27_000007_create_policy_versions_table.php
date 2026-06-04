<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('effective_at');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_versions');
    }
};
