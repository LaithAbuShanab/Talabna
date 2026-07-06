<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Filament\Widgets\Concerns\ReadsDashboardPeriodFilter;
use App\Models\RestaurantSetting;
use App\Services\DashboardMetricsService;
use App\Support\Money;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * "أكثر المنتجات مبيعًا" — a plain custom Widget with its own Blade view
 * rather than a full Filament TableWidget: the underlying query is a
 * `GROUP BY product_id` aggregate (App\Services\DashboardMetricsService::
 * bestSellingProducts()), which doesn't map cleanly onto a real,
 * per-record Eloquent query the way Filament's Table component expects
 * (sorting/pagination/row-actions all assume ordinary model rows) — a
 * plain Blade loop over the already-cached aggregate array is simpler and
 * avoids forcing a non-CRUD read into CRUD-shaped infrastructure.
 */
class BestSellingProductsWidget extends Widget
{
    use InteractsWithPageFilters;
    use ReadsDashboardPeriodFilter;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.best-selling-products';

    public static function canView(): bool
    {
        return in_array(Auth::user()?->role, [UserRole::SuperAdmin, UserRole::Manager, UserRole::Cashier], true);
    }

    protected function getViewData(): array
    {
        $currencyCode = RestaurantSetting::current()->currency_code;
        $products = app(DashboardMetricsService::class)->bestSellingProducts($this->currentPeriod());

        return [
            'products' => array_map(fn (array $row): array => [
                ...$row,
                'revenue_formatted' => Money::format($row['revenue_amount'], $currencyCode)['formatted'],
            ], $products),
            'currencyCode' => $currencyCode,
        ];
    }
}
