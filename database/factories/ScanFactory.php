<?php

namespace Database\Factories;

use App\Enums\ScanStatus;
use App\Enums\ScanTrigger;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Scan>
 */
class ScanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'status' => ScanStatus::Queued,
            'trigger' => ScanTrigger::Manual,
            'pages_crawled' => 0,
            'started_at' => null,
            'finished_at' => null,
            'error' => null,
        ];
    }

    public function complete(int $pages = 42): static
    {
        return $this->state(fn () => [
            'status' => ScanStatus::Complete,
            'pages_crawled' => $pages,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }

    public function failed(string $error = 'Site unreachable'): static
    {
        return $this->state(fn () => [
            'status' => ScanStatus::Failed,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
            'error' => $error,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['trigger' => ScanTrigger::Scheduled]);
    }
}
