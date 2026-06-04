<?php

namespace Database\Factories;

use App\Enums\DomainVerifyStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Domain>
 */
class DomainFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'domain_uid' => 'dom_'.Str::lower(Str::random(24)),
            'hostname' => fake()->unique()->domainName(),
            'verify_status' => DomainVerifyStatus::Pending,
            'consent_expiry_days' => 365,
            'scheduled_scan_enabled' => false,
            'scan_frequency' => null,
            'last_scanned_at' => null,
            'banner_live' => false,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'verify_status' => DomainVerifyStatus::Verified,
            'banner_live' => true,
        ]);
    }

    public function scheduledScans(string $frequency = 'monthly'): static
    {
        return $this->state(fn () => [
            'scheduled_scan_enabled' => true,
            'scan_frequency' => $frequency,
        ]);
    }
}
