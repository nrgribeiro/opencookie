<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('draft');   // draft|published|archived
            $table->json('layout');                        // type, position, theme, colors, logo
            $table->json('content');                       // per-language texts + button labels
            $table->json('languages');                     // ["en","pt","de"]
            $table->string('default_language')->default('en');
            $table->string('policy_url')->nullable();
            $table->json('consent_mode_map')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'version']);
        });

        // One published config per domain (Postgres partial unique index).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX one_published_per_domain ON banner_configs (domain_id) WHERE status = 'published'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_configs');
    }
};
