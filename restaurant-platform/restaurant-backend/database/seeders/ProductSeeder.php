<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\OptionGroup;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * A handful of demo products per category, with the "Size" and
     * "Extra Toppings" option groups (see OptionSeeder) attached to the
     * ones that make sense for them.
     */
    public function run(): void
    {
        $products = [
            'Burgers' => [
                ['name' => 'Classic Beef Burger', 'price' => 450, 'options' => ['Size']],
                ['name' => 'Chicken Burger', 'price' => 400, 'options' => ['Size']],
            ],
            'Pizza' => [
                ['name' => 'Margherita Pizza', 'price' => 600, 'options' => ['Size', 'Extra Toppings']],
                ['name' => 'Pepperoni Pizza', 'price' => 700, 'options' => ['Size', 'Extra Toppings']],
            ],
            'Sandwiches' => [
                ['name' => 'Club Sandwich', 'price' => 350, 'options' => []],
            ],
            'Drinks' => [
                ['name' => 'Soft Drink', 'price' => 100, 'options' => []],
                ['name' => 'Fresh Orange Juice', 'price' => 200, 'options' => []],
            ],
            'Desserts' => [
                ['name' => 'Chocolate Cake', 'price' => 250, 'options' => []],
            ],
        ];

        $optionGroups = OptionGroup::query()->get()->keyBy('name');

        foreach ($products as $categoryName => $items) {
            $category = Category::query()->where('name', $categoryName)->firstOrFail();

            foreach ($items as $index => $item) {
                $product = Product::query()->updateOrCreate(
                    ['slug' => Str::slug($item['name'])],
                    [
                        'category_id' => $category->id,
                        'name' => $item['name'],
                        'price_amount' => $item['price'],
                        'is_available' => true,
                        'sort_order' => $index,
                    ],
                );

                $groupIds = collect($item['options'])
                    ->map(fn (string $name) => $optionGroups->get($name)?->id)
                    ->filter()
                    ->mapWithKeys(fn (int $id) => [$id => ['is_required' => true, 'sort_order' => 0]]);

                if ($groupIds->isNotEmpty()) {
                    $product->optionGroups()->sync($groupIds);
                }
            }
        }
    }
}
