<?php

namespace Database\Seeders;

use App\Models\Tier;
use Illuminate\Database\Seeder;

class TiersSeeder extends Seeder
{
    /**
     * Seed the default account tiers (US-ADMIN-5). Free is the default tier
     * and preserves the previous hard-coded free-tier limits (functional-spec §8).
     */
    public function run(): void
    {
        $tiers = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'max_domains' => 1,
                'max_scan_pages' => 100,
                'monthly_pageview_cap' => 50000,
                'scheduled_scans_allowed' => false,
                'is_default' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'max_domains' => 10,
                'max_scan_pages' => 500,
                'monthly_pageview_cap' => 1000000,
                'scheduled_scans_allowed' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'max_domains' => null,           // unlimited
                'max_scan_pages' => 2000,
                'monthly_pageview_cap' => null,  // unlimited
                'scheduled_scans_allowed' => true,
                'is_default' => false,
            ],
        ];

        foreach ($tiers as $tier) {
            Tier::updateOrCreate(['slug' => $tier['slug']], $tier);
        }
    }
}
