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
            // Not a unique DB column — fake()->unique() on the very small
            // citySuffix() pool exhausts after a few hundred factory calls
            // across the whole suite, so a random number is combined in
            // instead to stay collision-resistant without needing "unique".
            'name' => fake()->citySuffix().' Zone #'.fake()->numberBetween(1, 1000000),
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
