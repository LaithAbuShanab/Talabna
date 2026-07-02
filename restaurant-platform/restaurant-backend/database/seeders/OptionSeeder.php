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
                'name_ar' => 'الحجم',
                'values' => [
                    ['Small', 0, 'صغير'],
                    ['Medium', 200, 'وسط'],
                    ['Large', 400, 'كبير'],
                ],
                'default' => 'Medium',
            ],
            'Bread Type' => [
                'type' => OptionSelectionType::Single,
                'sort_order' => 1,
                'name_ar' => 'نوع الخبز',
                'values' => [
                    ['White Bun', 0, 'خبز أبيض'],
                    ['Whole Wheat Bun', 50, 'خبز أسمر'],
                    ['Sesame Bun', 50, 'خبز بالسمسم'],
                ],
                'default' => 'White Bun',
            ],
            'Extras' => [
                'type' => OptionSelectionType::Multiple,
                'sort_order' => 2,
                'name_ar' => 'إضافات',
                'values' => [
                    ['Extra Cheese', 150, 'جبنة إضافية'],
                    ['Mushrooms', 100, 'مشروم'],
                    ['Olives', 100, 'زيتون'],
                    ['Jalapenos', 100, 'هالبينو'],
                ],
                'default' => null,
            ],
            'Sauces' => [
                'type' => OptionSelectionType::Multiple,
                'sort_order' => 3,
                'name_ar' => 'صلصات',
                'values' => [
                    ['Ketchup', 0, 'كاتشب'],
                    ['Garlic Sauce', 50, 'صلصة ثوم'],
                    ['BBQ Sauce', 50, 'صلصة باربكيو'],
                    ['Spicy Sauce', 50, 'صلصة حارة'],
                ],
                'default' => null,
            ],
        ];

        foreach ($groups as $groupName => $group) {
            $optionGroup = OptionGroup::query()->updateOrCreate(
                ['name' => $groupName],
                [
                    'name_ar' => $group['name_ar'],
                    'selection_type' => $group['type'],
                    'sort_order' => $group['sort_order'],
                ],
            );

            foreach ($group['values'] as $index => [$valueName, $priceDelta, $valueNameAr]) {
                $optionGroup->values()->updateOrCreate(
                    ['name' => $valueName],
                    [
                        'name_ar' => $valueNameAr,
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
