<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * No WithoutModelEvents here on purpose: Order relies on a creating()
     * model event to generate its order_number (see App\Models\Order), so
     * seeders that create orders must keep model events enabled.
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
        ]);

        if (User::query()->where('email', 'admin@example.com')->doesntExist()) {
            User::factory()->admin()->create([
                'name' => 'Admin',
                'email' => 'admin@example.com',
            ]);
        }

        if (User::query()->where('email', 'test@example.com')->doesntExist()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
