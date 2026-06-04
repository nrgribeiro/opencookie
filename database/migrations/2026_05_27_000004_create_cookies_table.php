<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('provider')->nullable();
            $table->string('category')->default('unclassified');
            $table->text('purpose')->nullable();
            $table->string('expiry')->nullable();            // "1 year" | "session"
            $table->string('type');                          // http|script|local_storage|session_storage|pixel
            $table->string('source_domain')->nullable();
            $table->boolean('is_first_party')->default(true);
            $table->string('status')->default('new');        // active|new|not_seen
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'name', 'source_domain']);
            $table->index(['domain_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookies');
    }
};
