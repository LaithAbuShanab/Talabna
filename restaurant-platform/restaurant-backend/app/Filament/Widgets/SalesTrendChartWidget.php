<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\RestaurantSetting;
use App\Services\DashboardMetricsService;
use App\Support\Money;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

/**
 * "المبيعات خلال آخر 7 أو 30 يومًا" — deliberately uses ChartWidget's own
 * built-in local filter (`$filter`/`getFilters()`), not the Dashboard's
 * shared page-level period selector: the task asks for exactly this one
 * choice (7 vs. 30 days) on this one chart, independent of whatever
 * broader period is selected elsewhere on the page.
 */
class SalesTrendChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '7';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return in_array(Auth::user()?->role, [UserRole::SuperAdmin, UserRole::Manager, UserRole::Cashier], true);
    }

    public function getHeading(): string
    {
        return 'Sales trend';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): array
    {
        return [
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
        ];
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? 7);
        $currencyCode = RestaurantSetting::current()->currency_code;
        $series = app(DashboardMetricsService::class)->salesTrend($days);

        return [
            'datasets' => [
                [
                    'label' => "Revenue ({$currencyCode})",
                    'data' => array_map(
                        fn (int $amountMinor): float => Money::toMajorUnits($amountMinor, $currencyCode),
                        array_values($series),
                    ),
                ],
            ],
            'labels' => array_keys($series),
        ];
    }
}
