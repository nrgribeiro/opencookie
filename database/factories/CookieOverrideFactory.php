<?php

namespace Database\Factories;

use App\Enums\CookieCategory;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CookieOverride>
 */
class CookieOverrideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'cookie_name' => fake()->unique()->lexify('_??cookie????'),
            'source_domain' => fake()->optional()->domainName(),
            'category' => fake()->randomElement(CookieCategory::assignable()),
            'provider' => fake()->randomElement(['Google', 'Meta', 'Self']),
            'purpose' => fake()->sentence(),
        ];
    }
}
