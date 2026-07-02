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
            ['name' => 'Nearby (0-3km)', 'fee' => 300, 'minutes' => 20, 'radius' => 3000],
            ['name' => 'Extended (3-8km)', 'fee' => 700, 'minutes' => 40, 'radius' => 8000],
        ];

        foreach ($zones as $index => $zone) {
            DeliveryZone::query()->updateOrCreate(
                ['name' => $zone['name']],
                [
                    'delivery_fee_amount' => $zone['fee'],
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
