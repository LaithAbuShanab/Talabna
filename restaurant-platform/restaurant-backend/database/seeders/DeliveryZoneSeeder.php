<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['name' => 'Nearby (0-3km)', 'fee' => 300, 'min_order' => 500, 'minutes' => 20, 'radius' => 3000],
            ['name' => 'Extended (3-8km)', 'fee' => 700, 'min_order' => 1000, 'minutes' => 40, 'radius' => 8000],
            ['name' => 'Far (8-15km)', 'fee' => 1200, 'min_order' => 1500, 'minutes' => 60, 'radius' => 15000],
        ];

        foreach ($zones as $index => $zone) {
            DeliveryZone::query()->updateOrCreate(
                ['name' => $zone['name']],
                [
                    'delivery_fee_amount' => $zone['fee'],
                    'min_order_amount' => $zone['min_order'],
                    'estimated_minutes' => $zone['minutes'],
                    'latitude' => '31.9539000',
                    'longitude' => '35.9106000',
                    'radius_meters' => $zone['radius'],
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }
}
