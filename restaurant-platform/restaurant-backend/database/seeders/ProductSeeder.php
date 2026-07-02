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
     * 17 demo products across the 5 seeded categories, each with a local
     * placeholder image (public/images/placeholders/*.svg — no external
     * image service dependency) and the option groups that make sense for
     * it attached via the product_option_groups pivot.
     */
    public function run(): void
    {
        $products = [
            'Burgers' => [
                ['name' => 'Classic Beef Burger', 'price' => 450, 'options' => ['Bread Type', 'Extras', 'Sauces']],
                ['name' => 'Chicken Burger', 'price' => 400, 'options' => ['Bread Type', 'Extras', 'Sauces']],
                ['name' => 'Double Beef Burger', 'price' => 600, 'options' => ['Bread Type', 'Extras', 'Sauces']],
                ['name' => 'Veggie Burger', 'price' => 380, 'options' => ['Bread Type', 'Extras', 'Sauces']],
            ],
            'Pizza' => [
                ['name' => 'Margherita Pizza', 'price' => 600, 'options' => ['Size', 'Extras']],
                ['name' => 'Pepperoni Pizza', 'price' => 700, 'options' => ['Size', 'Extras']],
                ['name' => 'Vegetable Pizza', 'price' => 620, 'options' => ['Size', 'Extras']],
                ['name' => 'BBQ Chicken Pizza', 'price' => 720, 'options' => ['Size', 'Extras']],
            ],
            'Sandwiches' => [
                ['name' => 'Club Sandwich', 'price' => 350, 'options' => ['Bread Type', 'Sauces']],
                ['name' => 'Grilled Cheese Sandwich', 'price' => 300, 'options' => ['Bread Type', 'Sauces']],
                ['name' => 'Tuna Sandwich', 'price' => 370, 'options' => ['Bread Type', 'Sauces']],
            ],
            'Drinks' => [
                ['name' => 'Soft Drink', 'price' => 100, 'options' => []],
                ['name' => 'Fresh Orange Juice', 'price' => 200, 'options' => []],
                ['name' => 'Iced Tea', 'price' => 150, 'options' => []],
                ['name' => 'Bottled Water', 'price' => 50, 'options' => []],
            ],
            'Desserts' => [
                ['name' => 'Chocolate Cake', 'price' => 250, 'options' => []],
                ['name' => 'Cheesecake', 'price' => 280, 'options' => []],
            ],
        ];

        $optionGroups = OptionGroup::query()->get()->keyBy('name');

        foreach ($products as $categoryName => $items) {
            $category = Category::query()->where('name', $categoryName)->firstOrFail();
            $imagePath = 'images/placeholders/'.Str::slug($categoryName).'.svg';

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

                $product->images()->updateOrCreate(
                    ['path' => $imagePath],
                    ['sort_order' => 0, 'is_primary' => true],
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
