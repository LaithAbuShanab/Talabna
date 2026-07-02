<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminActivityLog>
 */
class AdminActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'action' => 'order.status_changed',
            'subject_type' => null,
            'subject_id' => null,
            'description' => fake()->sentence(),
            'metadata' => null,
        ];
    }
}
