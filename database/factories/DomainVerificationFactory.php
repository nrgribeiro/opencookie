<?php

namespace Database\Factories;

use App\Enums\VerificationMethod;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DomainVerification>
 */
class DomainVerificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'method' => fake()->randomElement(VerificationMethod::cases()),
            'token' => 'cmp-verify-'.Str::lower(Str::random(32)),
            'verified_at' => null,
            'last_checked_at' => null,
            'last_error' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'verified_at' => now(),
            'last_checked_at' => now(),
            'last_error' => null,
        ]);
    }

    public function failed(string $error = 'Token not found'): static
    {
        return $this->state(fn () => [
            'verified_at' => null,
            'last_checked_at' => now(),
            'last_error' => $error,
        ]);
    }
}
