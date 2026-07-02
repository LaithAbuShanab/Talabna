<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OptionSelectionType;
use App\Models\OptionGroup;
use Illuminate\Database\Seeder;

class OptionSeeder extends Seeder
{
    public function run(): void
    {
        $size = OptionGroup::query()->updateOrCreate(
            ['name' => 'Size'],
            ['selection_type' => OptionSelectionType::Single, 'sort_order' => 0],
        );

        foreach ([['Small', 0], ['Medium', 200], ['Large', 400]] as $index => [$name, $priceDelta]) {
            $size->values()->updateOrCreate(
                ['name' => $name],
                ['price_delta_amount' => $priceDelta, 'is_default' => $name === 'Medium', 'sort_order' => $index, 'is_active' => true],
            );
        }

        $toppings = OptionGroup::query()->updateOrCreate(
            ['name' => 'Extra Toppings'],
            ['selection_type' => OptionSelectionType::Multiple, 'sort_order' => 1],
        );

        foreach ([['Extra Cheese', 150], ['Mushrooms', 100], ['Olives', 100], ['Jalapenos', 100]] as $index => [$name, $priceDelta]) {
            $toppings->values()->updateOrCreate(
                ['name' => $name],
                ['price_delta_amount' => $priceDelta, 'is_default' => false, 'sort_order' => $index, 'is_active' => true],
            );
        }
    }
}
