<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Filament\Widgets\Concerns\ReadsDashboardPeriodFilter;
use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

/**
 * "توزيع طرق الدفع" — payment-related, so kept in the same
 * super_admin/manager/cashier tier as the financial widgets.
 */
class PaymentMethodDistributionWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use ReadsDashboardPeriodFilter;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return in_array(Auth::user()?->role, [UserRole::SuperAdmin, UserRole::Manager, UserRole::Cashier], true);
    }

    public function getHeading(): string
    {
        return 'Payment methods';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $distribution = app(DashboardMetricsService::class)->paymentMethodDistribution($this->currentPeriod());

        return [
            'datasets' => [
                [
                    'data' => array_values($distribution),
                    'backgroundColor' => ['#6b7280', '#3b82f6'],
                ],
            ],
            'labels' => array_map(
                fn (string $value): string => PaymentMethod::from($value)->getLabel(),
                array_keys($distribution),
            ),
        ];
    }
}
