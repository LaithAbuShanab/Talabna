<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Enums\DashboardPeriod;

/**
 * Shared by every period-aware Dashboard widget
 * (App\Filament\Widgets\{OrdersOverviewWidget, BestSellingProductsWidget,
 * OrderStatusDistributionWidget, PaymentMethodDistributionWidget}, used
 * together with `Filament\Widgets\Concerns\InteractsWithPageFilters`).
 *
 * Deliberately defensive about the shape of `$this->pageFilters['period']`:
 * this project already hit a real bug elsewhere
 * (App\Filament\Resources\Coupons\Schemas\CouponForm, see
 * docs/ADMIN_COUPONS.md) where a `Select::make(...)->options(EnumClass::class)`
 * resolved its live state to the *enum case itself* via `Get`, not its
 * `->value` string, in a way that wasn't obvious from the Filament docs.
 * Rather than assume `pageFilters` behaves one way or the other, this
 * accepts either an already-cast `DashboardPeriod` or a plain string and
 * normalizes both — verified against both shapes in
 * tests/Feature/Filament/DashboardWidgetsTest.php.
 */
trait ReadsDashboardPeriodFilter
{
    protected function currentPeriod(): DashboardPeriod
    {
        $value = $this->pageFilters['period'] ?? null;

        if ($value instanceof DashboardPeriod) {
            return $value;
        }

        return DashboardPeriod::tryFrom((string) $value) ?? DashboardPeriod::Today;
    }
}
