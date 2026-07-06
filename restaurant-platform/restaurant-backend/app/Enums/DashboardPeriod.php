<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Carbon;

/**
 * The Dashboard's shared time-period selector ("اختيار فترة زمنية") — one
 * enum, one `range()` method, reused by every period-aware widget via
 * App\Services\DashboardMetricsService, so "today" always means the exact
 * same `[start, end]` instant pair everywhere on the page.
 */
enum DashboardPeriod: string implements HasLabel
{
    case Today = 'today';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case ThisMonth = 'this_month';

    public function getLabel(): string
    {
        return match ($this) {
            self::Today => 'Today',
            self::Last7Days => 'Last 7 days',
            self::Last30Days => 'Last 30 days',
            self::ThisMonth => 'This month',
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        $now = now();

        return match ($this) {
            self::Today => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            self::Last7Days => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            self::Last30Days => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            self::ThisMonth => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        };
    }
}
