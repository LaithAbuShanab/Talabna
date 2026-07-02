<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OptionValue;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemOption>
 */
class OrderItemOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'option_value_id' => OptionValue::factory(),
            'option_group_name' => 'Size',
            'option_value_name' => fake()->randomElement(['Small', 'Medium', 'Large']),
            'price_delta_amount' => fake()->numberBetween(0, 300),
        ];
    }
}
