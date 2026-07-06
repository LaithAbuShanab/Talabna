<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Filament\Widgets\Concerns\ReadsDashboardPeriodFilter;
use App\Models\RestaurantSetting;
use App\Services\DashboardMetricsService;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * The financial/reporting half of the Dashboard overview — "الطلبات
 * اليوم", "المبيعات اليوم", "متوسط قيمة الطلب", "الطلبات الملغاة" — all
 * scoped to the shared page-level period filter (App\Filament\Pages\
 * Dashboard). See App\Services\DashboardMetricsService's docblock for the
 * revenue-recognition decision and caching strategy.
 *
 * Restricted to super_admin/manager/cashier: kitchen/support don't need
 * revenue visibility — see docs/ADMIN_DASHBOARD.md "Who sees what."
 * App\Filament\Widgets\OperationalStatusWidget is the counterpart every
 * admin role can see.
 */
class OrdersOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use ReadsDashboardPeriodFilter;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return in_array(Auth::user()?->role, [UserRole::SuperAdmin, UserRole::Manager, UserRole::Cashier], true);
    }

    protected function getStats(): array
    {
        $period = $this->currentPeriod();
        $service = app(DashboardMetricsService::class);
        $currencyCode = RestaurantSetting::current()->currency_code;

        return [
            Stat::make('Orders', (string) $service->ordersCount($period))
                ->description($period->getLabel())
                ->icon(Heroicon::OutlinedShoppingBag)
                ->color('info'),
            Stat::make('Revenue', Money::format($service->revenueAmount($period), $currencyCode)['formatted'])
                ->description('Delivered orders only — see docs/ADMIN_DASHBOARD.md')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Avg. order value', Money::format($service->averageOrderValueAmount($period), $currencyCode)['formatted'])
                ->description('Per delivered order')
                ->icon(Heroicon::OutlinedCalculator)
                ->color('primary'),
            Stat::make('Cancelled', (string) $service->cancelledOrdersCount($period))
                ->description($period->getLabel())
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger'),
        ];
    }
}
