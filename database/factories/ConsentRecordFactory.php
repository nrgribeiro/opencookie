<?php

namespace Database\Factories;

use App\Enums\ConsentMethod;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConsentRecord>
 */
class ConsentRecordFactory extends Factory
{
    public function definition(): array
    {
        $method = fake()->randomElement(ConsentMethod::cases());
        $createdAt = fake()->dateTimeBetween('-60 days', 'now');

        return [
            'domain_id' => Domain::factory(),
            'consent_id' => (string) Str::uuid(),
            'method' => $method,
            'categories' => $this->categoriesFor($method),
            'banner_version' => 1,
            'policy_version' => 1,
            'consent_text_hash' => 'sha256:'.hash('sha256', 'banner-text-v1'),
            'ip_hash' => hash('sha256', fake()->ipv4().'salt'),
            'user_agent' => fake()->userAgent(),
            'language' => 'en',
            'created_at' => $createdAt,
            'expires_at' => (clone $createdAt)->modify('+365 days'),
        ];
    }

    private function categoriesFor(ConsentMethod $method): array
    {
        return match ($method) {
            ConsentMethod::AcceptAll => [
                'necessary' => true, 'preferences' => true, 'statistics' => true, 'marketing' => true,
            ],
            ConsentMethod::RejectAll => [
                'necessary' => true, 'preferences' => false, 'statistics' => false, 'marketing' => false,
            ],
            ConsentMethod::Custom => [
                'necessary' => true,
                'preferences' => fake()->boolean(),
                'statistics' => fake()->boolean(),
                'marketing' => fake()->boolean(),
            ],
        };
    }

    public function method(ConsentMethod $method): static
    {
        return $this->state(fn () => [
            'method' => $method,
            'categories' => $this->categoriesFor($method),
        ]);
    }
}
