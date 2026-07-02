<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Burgers' => 'برجر',
            'Pizza' => 'بيتزا',
            'Sandwiches' => 'ساندويشات',
            'Drinks' => 'مشروبات',
            'Desserts' => 'حلويات',
        ];

        foreach (array_keys($names) as $index => $name) {
            Category::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'name_ar' => $names[$name],
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }
}
