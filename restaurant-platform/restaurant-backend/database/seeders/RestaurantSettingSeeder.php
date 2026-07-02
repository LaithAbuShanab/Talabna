<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RestaurantSetting;
use Illuminate\Database\Seeder;

class RestaurantSettingSeeder extends Seeder
{
    public function run(): void
    {
        RestaurantSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'restaurant_name' => 'Talabna Restaurant',
                'phone' => '+962700000000',
                'email' => 'contact@talabna.example',
                'address' => 'Amman, Jordan',
                'latitude' => '31.9539000',
                'longitude' => '35.9106000',
                'currency_code' => 'JOD',
                'default_delivery_fee_amount' => 500,
                'min_order_amount' => 1000,
                'default_preparation_minutes' => 25,
                'is_accepting_orders' => true,
            ],
        );
    }
}
