<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptionValue>
 */
class OptionValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'option_group_id' => OptionGroup::factory(),
            'name' => fake()->randomElement(['Small', 'Medium', 'Large', 'Extra Cheese', 'Mild', 'Spicy']),
            'price_delta_amount' => fake()->numberBetween(0, 500),
            'is_default' => false,
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
