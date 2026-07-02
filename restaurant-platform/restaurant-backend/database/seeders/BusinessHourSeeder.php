<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessHour;
use Illuminate\Database\Seeder;

class BusinessHourSeeder extends Seeder
{
    /**
     * One row per weekday, 10:00-23:00 every day except Friday (closed),
     * as a simple, editable starting point.
     */
    public function run(): void
    {
        for ($day = 0; $day <= 6; $day++) {
            $isFriday = $day === 5;

            BusinessHour::query()->updateOrCreate(
                ['day_of_week' => $day],
                [
                    'opens_at' => $isFriday ? null : '10:00:00',
                    'closes_at' => $isFriday ? null : '23:00:00',
                    'is_closed' => $isFriday,
                ],
            );
        }
    }
}
