<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BusinessHourException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessHourException>
 */
class BusinessHourExceptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'is_closed' => true,
            'opens_at' => null,
            'closes_at' => null,
            'note' => fake()->words(2, true),
        ];
    }

    public function customHours(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_closed' => false,
            'opens_at' => '12:00:00',
            'closes_at' => '18:00:00',
        ]);
    }
}
