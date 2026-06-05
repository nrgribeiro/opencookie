<?php

namespace App\Providers;

use App\Services\Scanner\HttpSiteScanner;
use App\Services\Scanner\PlaywrightSiteScanner;
use App\Services\Scanner\SiteScanner;
use App\Services\Verification\DnsTxtRecordResolver;
use App\Services\Verification\TxtRecordResolver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TxtRecordResolver::class, DnsTxtRecordResolver::class);
        $this->app->bind(SiteScanner::class, fn () => config('scanner.driver') === 'playwright'
            ? new PlaywrightSiteScanner
            : new HttpSiteScanner);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiters();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Rate limiters for the public Consent Ingest API (technical-spec §8).
     * Per-IP + per-domain to protect against log flooding.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('ingest', fn (Request $request) => [
            Limit::perMinute(120)->by('ip:'.$request->ip()),
            Limit::perMinute(600)->by('dom:'.$request->route('domainUid')),
        ]);
    }
}
