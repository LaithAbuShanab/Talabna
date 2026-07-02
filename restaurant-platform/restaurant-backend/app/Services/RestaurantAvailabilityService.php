<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BusinessHour;
use App\Models\RestaurantSetting;

/**
 * The single place "is the restaurant open right now" is computed —
 * extracted out of App\Actions\CreateOrderAction so the public
 * GET /api/v1/restaurant/is-open endpoint and checkout use the exact same
 * rule, rather than two copies that could drift apart.
 */
final class RestaurantAvailabilityService
{
    public function isOpenNow(?RestaurantSetting $settings = null): bool
    {
        $settings ??= RestaurantSetting::current();

        if (! $settings->is_accepting_orders) {
            return false;
        }

        $now = now();
        $businessHour = BusinessHour::query()->where('day_of_week', $now->dayOfWeek)->first();

        if (! $businessHour instanceof BusinessHour || $businessHour->is_closed) {
            return false;
        }

        if ($businessHour->opens_at === null || $businessHour->closes_at === null) {
            return false;
        }

        $currentTime = $now->format('H:i:s');

        return $currentTime >= $businessHour->opens_at && $currentTime <= $businessHour->closes_at;
    }
}
