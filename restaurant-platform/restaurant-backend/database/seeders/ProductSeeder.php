<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OptionSelectionType;
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
                ['name' => 'Classic Beef Burger', 'name_ar' => 'برجر لحم كلاسيكي', 'price' => 450, 'options' => ['Bread Type', 'Extras', 'Sauces']],
                ['name' => 'Chicken Burger', 'name_ar' => 'برجر دجاج', 'price' => 400, 'options' => ['Bread Type', 'Extras', 'Sauces']],
                ['name' => 'Double Beef Burger', 'name_ar' => 'برجر لحم مزدوج', 'price' => 600, 'options' => ['Bread Type', 'Extras', 'Sauces']],
                ['name' => 'Veggie Burger', 'name_ar' => 'برجر خضار', 'price' => 380, 'options' => ['Bread Type', 'Extras', 'Sauces']],
            ],
            'Pizza' => [
                ['name' => 'Margherita Pizza', 'name_ar' => 'بيتزا مارغريتا', 'price' => 600, 'options' => ['Size', 'Extras']],
                ['name' => 'Pepperoni Pizza', 'name_ar' => 'بيتزا ببيروني', 'price' => 700, 'options' => ['Size', 'Extras']],
                ['name' => 'Vegetable Pizza', 'name_ar' => 'بيتزا خضار', 'price' => 620, 'options' => ['Size', 'Extras']],
                ['name' => 'BBQ Chicken Pizza', 'name_ar' => 'بيتزا دجاج باربكيو', 'price' => 720, 'options' => ['Size', 'Extras']],
            ],
            'Sandwiches' => [
                ['name' => 'Club Sandwich', 'name_ar' => 'ساندويش كلوب', 'price' => 350, 'options' => ['Bread Type', 'Sauces']],
                ['name' => 'Grilled Cheese Sandwich', 'name_ar' => 'ساندويش جبنة مشوية', 'price' => 300, 'options' => ['Bread Type', 'Sauces']],
                ['name' => 'Tuna Sandwich', 'name_ar' => 'ساندويش تونة', 'price' => 370, 'options' => ['Bread Type', 'Sauces']],
            ],
            'Drinks' => [
                ['name' => 'Soft Drink', 'name_ar' => 'مشروب غازي', 'price' => 100, 'options' => []],
                ['name' => 'Fresh Orange Juice', 'name_ar' => 'عصير برتقال طازج', 'price' => 200, 'options' => []],
                ['name' => 'Iced Tea', 'name_ar' => 'شاي مثلج', 'price' => 150, 'options' => []],
                ['name' => 'Bottled Water', 'name_ar' => 'مياه معدنية', 'price' => 50, 'options' => []],
            ],
            'Desserts' => [
                ['name' => 'Chocolate Cake', 'name_ar' => 'كيك شوكولاتة', 'price' => 250, 'options' => []],
                ['name' => 'Cheesecake', 'name_ar' => 'تشيز كيك', 'price' => 280, 'options' => []],
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
                        'name_ar' => $item['name_ar'],
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
                    ->map(fn (string $name) => $optionGroups->get($name))
                    ->filter()
                    ->mapWithKeys(fn (OptionGroup $group) => [
                        $group->id => [
                            // Single-select groups (Size, Bread Type) force a choice;
                            // multi-select groups (Extras, Sauces) are optional add-ons.
                            'is_required' => $group->selection_type === OptionSelectionType::Single,
                            'sort_order' => 0,
                        ],
                    ]);

                if ($groupIds->isNotEmpty()) {
                    $product->optionGroups()->sync($groupIds);
                }
            }
        }
    }
}
