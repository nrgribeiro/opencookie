<?php

namespace Database\Factories;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PolicyVersion>
 */
class PolicyVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'version' => 1,
            'effective_at' => now(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function version(int $version): static
    {
        return $this->state(fn () => ['version' => $version]);
    }
}
