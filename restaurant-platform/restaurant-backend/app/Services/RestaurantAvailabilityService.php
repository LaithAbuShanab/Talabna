<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BusinessHour;
use App\Models\BusinessHourException;
use App\Models\RestaurantSetting;

/**
 * The single place "is the restaurant open right now" is computed —
 * extracted out of App\Actions\CreateOrderAction so the public
 * GET /api/v1/restaurant/is-open endpoint and checkout use the exact same
 * rule, rather than two copies that could drift apart.
 *
 * Checks, in order: 1) is_accepting_orders, 2) today's
 * BusinessHourException (a public holiday or other one-off override — if
 * one exists for today, it *replaces* the regular weekly schedule below
 * entirely, whether that means fully closed or a custom shift), 3) the
 * regular day_of_week BusinessHour rows for today, now zero or more (see
 * "أكثر من فترة في اليوم إن لزم" — a day can have more than one opening
 * period, e.g. lunch + dinner) — open if the current time falls inside
 * *any* of them.
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

        $exception = BusinessHourException::query()->where('date', $now->toDateString())->first();

        if ($exception instanceof BusinessHourException) {
            if ($exception->is_closed || $exception->opens_at === null || $exception->closes_at === null) {
                return false;
            }

            $currentTime = $now->format('H:i:s');

            return $currentTime >= $exception->opens_at && $currentTime <= $exception->closes_at;
        }

        $periods = BusinessHour::query()
            ->where('day_of_week', $now->dayOfWeek)
            ->where('is_closed', false)
            ->whereNotNull('opens_at')
            ->whereNotNull('closes_at')
            ->get();

        $currentTime = $now->format('H:i:s');

        return $periods->contains(
            fn (BusinessHour $period): bool => $currentTime >= $period->opens_at && $currentTime <= $period->closes_at
        );
    }
}
