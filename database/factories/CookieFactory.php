<?php

namespace Database\Factories;

use App\Enums\CookieCategory;
use App\Enums\CookieStatus;
use App\Enums\CookieType;
use App\Models\Cookie;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cookie>
 */
class CookieFactory extends Factory
{
    public function definition(): array
    {
        $firstParty = fake()->boolean(60);

        return [
            'domain_id' => Domain::factory(),
            'scan_id' => null,
            'name' => fake()->unique()->lexify('_??cookie????'),
            'provider' => fake()->randomElement(['Google', 'Meta', 'Hotjar', 'Self', 'Cloudflare']),
            'category' => fake()->randomElement(CookieCategory::cases()),
            'purpose' => fake()->sentence(),
            'expiry' => fake()->randomElement(['session', '1 day', '30 days', '1 year', '2 years']),
            'type' => fake()->randomElement(CookieType::cases()),
            'source_domain' => $firstParty ? null : fake()->domainName(),
            'is_first_party' => $firstParty,
            'status' => CookieStatus::Active,
            'first_seen_at' => now()->subDays(30),
            'last_seen_at' => now(),
        ];
    }

    public function category(CookieCategory $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }

    public function unclassified(): static
    {
        return $this->state(fn () => [
            'category' => CookieCategory::Unclassified,
            'status' => CookieStatus::New,
        ]);
    }
}
