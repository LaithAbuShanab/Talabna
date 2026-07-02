<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OptionSelectionType;
use App\Models\OptionGroup;
use Illuminate\Database\Seeder;

class OptionSeeder extends Seeder
{
    /**
     * Four demo option groups covering both selection types:
     * Size/Bread Type (single-select) and Extras/Sauces (multi-select).
     * Idempotent: matched by group/value name, safe to re-run.
     */
    public function run(): void
    {
        $groups = [
            'Size' => [
                'type' => OptionSelectionType::Single,
                'sort_order' => 0,
                'values' => [
                    ['Small', 0],
                    ['Medium', 200],
                    ['Large', 400],
                ],
                'default' => 'Medium',
            ],
            'Bread Type' => [
                'type' => OptionSelectionType::Single,
                'sort_order' => 1,
                'values' => [
                    ['White Bun', 0],
                    ['Whole Wheat Bun', 50],
                    ['Sesame Bun', 50],
                ],
                'default' => 'White Bun',
            ],
            'Extras' => [
                'type' => OptionSelectionType::Multiple,
                'sort_order' => 2,
                'values' => [
                    ['Extra Cheese', 150],
                    ['Mushrooms', 100],
                    ['Olives', 100],
                    ['Jalapenos', 100],
                ],
                'default' => null,
            ],
            'Sauces' => [
                'type' => OptionSelectionType::Multiple,
                'sort_order' => 3,
                'values' => [
                    ['Ketchup', 0],
                    ['Garlic Sauce', 50],
                    ['BBQ Sauce', 50],
                    ['Spicy Sauce', 50],
                ],
                'default' => null,
            ],
        ];

        foreach ($groups as $groupName => $group) {
            $optionGroup = OptionGroup::query()->updateOrCreate(
                ['name' => $groupName],
                ['selection_type' => $group['type'], 'sort_order' => $group['sort_order']],
            );

            foreach ($group['values'] as $index => [$valueName, $priceDelta]) {
                $optionGroup->values()->updateOrCreate(
                    ['name' => $valueName],
                    [
                        'price_delta_amount' => $priceDelta,
                        'is_default' => $valueName === $group['default'],
                        'sort_order' => $index,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
