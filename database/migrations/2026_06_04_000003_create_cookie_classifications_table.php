<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-house cookie classification DB (functional-spec §8), seeded from the
 * Open Cookie Database via `cookies:import-ocd` and curated over time. The
 * scanner's CookieClassifier consults this table, falling back to a built-in
 * static map when it is empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookie_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // cookie / storage key name (or prefix when wildcard)
            $table->string('domain')->nullable();           // platform/provider domain, null = match any
            $table->enum('category', ['necessary', 'preferences', 'statistics', 'marketing', 'unclassified'])
                ->default('unclassified');
            $table->string('provider')->nullable();         // platform name (e.g. "Google Analytics")
            $table->string('provider_url')->nullable();
            $table->text('purpose')->nullable();            // description
            $table->string('retention')->nullable();        // e.g. "2 years"
            $table->boolean('is_wildcard')->default(false); // name is a prefix pattern
            $table->string('source')->default('ocd');       // provenance: ocd | manual
            $table->timestamps();

            $table->unique(['name', 'domain']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_classifications');
    }
};
