<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Widgets\Concerns\ReadsDashboardPeriodFilter;
use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

/**
 * "توزيع الطلبات حسب الحالة" — every count in every status, not just
 * revenue-relevant ones, so visible to every admin role (unlike the
 * financial widgets) — a kitchen-role user benefits from seeing the whole
 * pipeline shape too.
 */
class OrderStatusDistributionWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use ReadsDashboardPeriodFilter;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return Auth::user()?->role->isAdmin() ?? false;
    }

    public function getHeading(): string
    {
        return 'Orders by status';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $distribution = app(DashboardMetricsService::class)->orderStatusDistribution($this->currentPeriod());

        return [
            'datasets' => [
                [
                    'data' => array_values($distribution),
                    'backgroundColor' => ['#f59e0b', '#3b82f6', '#6b7280', '#6366f1', '#22c55e', '#16a34a', '#ef4444', '#dc2626'],
                ],
            ],
            'labels' => array_map(
                fn (string $value): string => OrderStatus::from($value)->getLabel(),
                array_keys($distribution),
            ),
        ];
    }
}
