<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with realistic, safe local
     * development data — see restaurant-backend/README.dev.md for the demo
     * login credentials this creates.
     *
     * No WithoutModelEvents here on purpose: Order relies on a creating()
     * model event to generate its order_number (see App\Models\Order), so
     * seeders that create orders must keep model events enabled.
     *
     * Order matters: CustomerSeeder/ProductSeeder/DeliveryZoneSeeder must
     * run before OrderSeeder, which builds demo orders out of them.
     */
    public function run(): void
    {
        $this->call([
            RestaurantSettingSeeder::class,
            BusinessHourSeeder::class,
            CategorySeeder::class,
            OptionSeeder::class,
            ProductSeeder::class,
            DeliveryZoneSeeder::class,
            CouponSeeder::class,
            AdminUserSeeder::class,
            CustomerSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
