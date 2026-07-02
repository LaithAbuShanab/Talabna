<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DevicePlatform;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => fake()->unique()->uuid(),
            'platform' => fake()->randomElement(DevicePlatform::cases()),
            'is_active' => true,
            'last_used_at' => now(),
        ];
    }
}
