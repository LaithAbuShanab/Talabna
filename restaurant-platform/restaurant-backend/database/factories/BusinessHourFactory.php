<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BusinessHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessHour>
 */
class BusinessHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'day_of_week' => fake()->numberBetween(0, 6),
            'opens_at' => '10:00:00',
            'closes_at' => '23:00:00',
            'is_closed' => false,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'opens_at' => null,
            'closes_at' => null,
            'is_closed' => true,
        ]);
    }
}
