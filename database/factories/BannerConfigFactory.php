<?php

namespace Database\Factories;

use App\Enums\BannerStatus;
use App\Models\BannerConfig;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BannerConfig>
 */
class BannerConfigFactory extends Factory
{
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'version' => 1,
            'status' => BannerStatus::Draft,
            'layout' => [
                'type' => 'box',
                'position' => 'bottom-left',
                'theme' => 'light',
                'colors' => ['accent' => '#2563eb'],
                'logo' => null,
            ],
            'content' => [
                'en' => [
                    'title' => 'We use cookies',
                    'body' => 'We use cookies to improve your experience.',
                    'acceptAll' => 'Accept all',
                    'rejectAll' => 'Reject all',
                    'customize' => 'Customize',
                ],
            ],
            'languages' => ['en'],
            'default_language' => 'en',
            'policy_url' => fake()->url(),
            'consent_mode_map' => [
                'analytics_storage' => ['statistics'],
                'ad_storage' => ['marketing'],
                'ad_user_data' => ['marketing'],
                'ad_personalization' => ['marketing'],
            ],
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => BannerStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function version(int $version): static
    {
        return $this->state(fn () => ['version' => $version]);
    }
}
