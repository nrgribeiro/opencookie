<?php

namespace Database\Factories;

use App\Models\Tier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tier>
 */
class TierFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'max_domains' => fake()->numberBetween(1, 10),
            'max_scan_pages' => fake()->randomElement([100, 500, 2000]),
            'monthly_pageview_cap' => fake()->numberBetween(50000, 1000000),
            'scheduled_scans_allowed' => fake()->boolean(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function unlimited(): static
    {
        return $this->state(['max_domains' => null, 'monthly_pageview_cap' => null]);
    }
}
