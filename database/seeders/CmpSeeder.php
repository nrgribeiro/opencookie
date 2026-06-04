<?php

namespace Database\Seeders;

use App\Enums\CookieCategory;
use App\Models\BannerConfig;
use App\Models\BannerImpression;
use App\Models\ConsentRecord;
use App\Models\Cookie;
use App\Models\CookieOverride;
use App\Models\Domain;
use App\Models\DomainVerification;
use App\Models\NotificationSetting;
use App\Models\PolicyVersion;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Database\Seeder;

class CmpSeeder extends Seeder
{
    /**
     * Build one coherent, verified domain graph for the Admin user
     * (free tier = 1 domain). Demonstrates every model + relationship.
     */
    public function run(): void
    {
        $user = User::firstWhere('email', 'test@example.com')
            ?? User::first()
            ?? User::factory()->create();

        $domain = Domain::factory()
            ->verified()
            ->for($user)
            ->create([
                'hostname' => 'example.com',
                'last_scanned_at' => now(),
            ]);

        // Verified ownership record.
        DomainVerification::factory()->verified()->for($domain)->create();

        // Notification settings.
        NotificationSetting::factory()->for($domain)->create();

        // Policy v1.
        PolicyVersion::factory()->version(1)->for($domain)->create();

        // Published banner v1.
        BannerConfig::factory()->published()->version(1)->for($domain)->create();

        // Completed scan + cookies across categories.
        $scan = Scan::factory()->complete()->for($domain)->create();

        foreach (CookieCategory::cases() as $category) {
            Cookie::factory()
                ->count(3)
                ->category($category)
                ->for($domain)
                ->create(['scan_id' => $scan->id]);
        }

        // A manual classification override.
        CookieOverride::factory()->for($domain)->create([
            'cookie_name' => '_ga',
            'source_domain' => 'google.com',
            'category' => CookieCategory::Statistics,
            'provider' => 'Google Analytics',
        ]);

        // Consent proof + impression aggregates (one row per distinct day).
        ConsentRecord::factory()->count(50)->for($domain)->create();

        foreach (range(1, 15) as $daysAgo) {
            BannerImpression::factory()->for($domain)->create([
                'day' => now()->subDays($daysAgo)->format('Y-m-d'),
                'banner_version' => 1,
                'language' => 'en',
            ]);
        }
    }
}
