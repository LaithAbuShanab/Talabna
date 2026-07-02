<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->citySuffix().' Zone',
            'delivery_fee_amount' => fake()->numberBetween(200, 1500),
            'min_order_amount' => fake()->optional()->numberBetween(500, 3000),
            'estimated_minutes' => fake()->numberBetween(15, 60),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'radius_meters' => fake()->numberBetween(500, 5000),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
