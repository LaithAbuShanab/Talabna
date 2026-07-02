<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RestaurantSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantSetting>
 */
class RestaurantSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'currency_code' => 'JOD',
            'default_delivery_fee_amount' => fake()->numberBetween(200, 1000),
            'min_order_amount' => fake()->numberBetween(0, 2000),
            'default_preparation_minutes' => fake()->numberBetween(10, 45),
            'is_accepting_orders' => true,
        ];
    }
}
