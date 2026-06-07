<?php

namespace Database\Factories;

use App\Models\Domain;
use App\Models\NotificationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationSetting>
 */
class NotificationSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'new_cookie_alerts' => true,
        ];
    }
}
