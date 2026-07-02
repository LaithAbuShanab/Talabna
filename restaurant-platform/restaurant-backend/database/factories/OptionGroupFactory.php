<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OptionSelectionType;
use App\Models\OptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptionGroup>
 */
class OptionGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Size', 'Extra Toppings', 'Spice Level', 'Add-ons']),
            'selection_type' => fake()->randomElement(OptionSelectionType::cases()),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    public function single(): static
    {
        return $this->state(fn (array $attributes) => [
            'selection_type' => OptionSelectionType::Single,
        ]);
    }

    public function multiple(): static
    {
        return $this->state(fn (array $attributes) => [
            'selection_type' => OptionSelectionType::Multiple,
        ]);
    }
}
