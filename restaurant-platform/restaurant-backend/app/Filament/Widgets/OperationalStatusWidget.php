<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * "الطلبات pending", "الطلبات قيد التحضير", "الطلبات المتأخرة" — deliberately
 * **not** scoped to the Dashboard's period filter: these reflect the
 * restaurant's current operational state right now, not a historical
 * report. A kitchen-role user watching this screen needs "how many are
 * late right now," never "how many were late during the last 30 days."
 *
 * Visible to every admin role (mirrors App\Policies\OrderPolicy::viewAny()
 * — the same tier that can see the Orders screen at all), unlike the
 * financial App\Filament\Widgets\OrdersOverviewWidget.
 */
class OperationalStatusWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return Auth::user()?->role->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $service = app(DashboardMetricsService::class);

        return [
            Stat::make('Pending', (string) $service->pendingOrdersCount())
                ->description('Awaiting acceptance')
                ->icon(Heroicon::OutlinedClock)
                ->color('warning'),
            Stat::make('Preparing', (string) $service->preparingOrdersCount())
                ->description('In the kitchen right now')
                ->icon(Heroicon::OutlinedFire)
                ->color('primary'),
            Stat::make('Late', (string) $service->lateOrdersCount())
                ->description('Past their expected time')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger'),
        ];
    }
}
