<?php

namespace Database\Factories;

use App\Models\BannerImpression;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BannerImpression>
 */
class BannerImpressionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'day' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'banner_version' => 1,
            'language' => 'en',
            'count' => fake()->numberBetween(50, 5000),
        ];
    }
}
