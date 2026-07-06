<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DashboardPeriod;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Every aggregation the admin Dashboard widgets need, each independently
 * cached — see docs/ADMIN_DASHBOARD.md for the full reasoning. Two
 * deliberate decisions apply throughout this class:
 *
 * 1. **Revenue is recognized at `delivered`, not `paid`.** `payment_status`
 *    can read `paid` before the food ever reaches the customer (a
 *    card-on-delivery charge captured up front) or stay `pending` well
 *    after delivery for cash settled at the door — neither reliably
 *    means "we actually fulfilled this sale." `status = delivered` is the
 *    one signal that's true exactly when the restaurant has completed its
 *    side of the transaction, and it's the same rule
 *    `Filament\Resources\Customers\{Tables\CustomersTable,
 *    Schemas\CustomerInfolist}` already used for "total spent" — kept
 *    consistent here rather than inventing a second revenue rule.
 *    Cancelled (and rejected) orders are automatically excluded, since
 *    only `delivered` ever counts.
 * 2. **Every amount returned here is a raw integer, smallest-currency-unit
 *    minor amount** — exactly what `orders.total_amount`/
 *    `order_items.line_total_amount` already store. Callers format through
 *    App\Support\Money at render time; nothing in this class ever divides
 *    into a float except `averageOrderValueAmount()`, which immediately
 *    rounds back to an integer.
 *
 * Caching is deliberately **TTL-only, no invalidation observer** (unlike
 * App\Services\MenuCacheService's catalog data): orders change constantly
 * (every status transition), so invalidating on write would defeat the
 * whole point of caching an aggregation query. A dashboard number being up
 * to a couple of minutes stale is an acceptable, explicit trade-off for a
 * reporting screen — see the two TTL constants below.
 */
final class DashboardMetricsService
{
    /** Reporting/financial aggregations: fine to lag a little longer. */
    private const int REPORTING_TTL_SECONDS = 120;

    /** "Right now" operational counts: kept fresher since staff act on them live. */
    private const int OPERATIONAL_TTL_SECONDS = 30;

    public function ordersCount(DashboardPeriod $period): int
    {
        return Cache::remember(
            "dashboard:orders_count:{$period->value}",
            self::REPORTING_TTL_SECONDS,
            function () use ($period): int {
                [$start, $end] = $period->range();

                return Order::query()->whereBetween('created_at', [$start, $end])->count();
            },
        );
    }

    public function revenueAmount(DashboardPeriod $period): int
    {
        return Cache::remember(
            "dashboard:revenue:{$period->value}",
            self::REPORTING_TTL_SECONDS,
            fn (): int => (int) $this->deliveredOrdersQuery($period)->sum('total_amount'),
        );
    }

    public function averageOrderValueAmount(DashboardPeriod $period): int
    {
        return Cache::remember(
            "dashboard:aov:{$period->value}",
            self::REPORTING_TTL_SECONDS,
            function () use ($period): int {
                $deliveredCount = $this->deliveredOrdersQuery($period)->count();

                if ($deliveredCount === 0) {
                    return 0;
                }

                return (int) round($this->revenueAmount($period) / $deliveredCount);
            },
        );
    }

    public function cancelledOrdersCount(DashboardPeriod $period): int
    {
        return Cache::remember(
            "dashboard:cancelled_count:{$period->value}",
            self::REPORTING_TTL_SECONDS,
            function () use ($period): int {
                [$start, $end] = $period->range();

                return Order::query()
                    ->where('status', OrderStatus::Cancelled)
                    ->whereBetween('created_at', [$start, $end])
                    ->count();
            },
        );
    }

    public function pendingOrdersCount(): int
    {
        return Cache::remember(
            'dashboard:pending_count',
            self::OPERATIONAL_TTL_SECONDS,
            fn (): int => Order::query()->where('status', OrderStatus::Pending)->count(),
        );
    }

    public function preparingOrdersCount(): int
    {
        return Cache::remember(
            'dashboard:preparing_count',
            self::OPERATIONAL_TTL_SECONDS,
            fn (): int => Order::query()->where('status', OrderStatus::Preparing)->count(),
        );
    }

    /**
     * Mirrors App\Filament\Resources\Orders\Tables\OrdersTable::isLate() —
     * an order past its expected delivery/pickup time that hasn't reached
     * a terminal status yet.
     */
    public function lateOrdersCount(): int
    {
        return Cache::remember(
            'dashboard:late_count',
            self::OPERATIONAL_TTL_SECONDS,
            fn (): int => Order::query()
                ->whereNotNull('expected_delivery_at')
                ->where('expected_delivery_at', '<', now())
                ->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Rejected])
                ->count(),
        );
    }

    /**
     * @return list<array{product_id: int, product_name: string, quantity: int, revenue_amount: int}>
     */
    public function bestSellingProducts(DashboardPeriod $period, int $limit = 5): array
    {
        return Cache::remember(
            "dashboard:best_selling:{$period->value}:{$limit}",
            self::REPORTING_TTL_SECONDS,
            function () use ($period, $limit): array {
                [$start, $end] = $period->range();

                // Grouped by product_id against order_items' own snapshotted
                // product_name (never the live Product, which may have been
                // renamed or soft-deleted since — same snapshotting
                // principle as everywhere else order data is read).
                $rows = OrderItem::query()
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->where('orders.status', OrderStatus::Delivered)
                    ->whereBetween('orders.created_at', [$start, $end])
                    ->whereNotNull('order_items.product_id')
                    ->selectRaw('order_items.product_id as product_id')
                    ->selectRaw('max(order_items.product_name) as product_name')
                    ->selectRaw('sum(order_items.quantity) as quantity')
                    ->selectRaw('sum(order_items.line_total_amount) as revenue_amount')
                    ->groupBy('order_items.product_id')
                    ->orderByDesc('quantity')
                    ->limit($limit)
                    ->get();

                return $rows->map(fn ($row): array => [
                    'product_id' => (int) $row->product_id,
                    'product_name' => $row->product_name,
                    'quantity' => (int) $row->quantity,
                    'revenue_amount' => (int) $row->revenue_amount,
                ])->all();
            },
        );
    }

    /**
     * Daily revenue for the last `$days` days (including today), oldest
     * first — for the sales trend chart. Every day in the range is
     * present even with zero revenue, so the chart's X axis never skips a
     * date.
     *
     * @return array<string, int> "Y-m-d" => revenue amount (minor units)
     */
    public function salesTrend(int $days): array
    {
        return Cache::remember(
            "dashboard:sales_trend:{$days}",
            self::REPORTING_TTL_SECONDS,
            function () use ($days): array {
                $start = now()->subDays($days - 1)->startOfDay();
                $end = now()->endOfDay();

                $rows = Order::query()
                    ->where('status', OrderStatus::Delivered)
                    ->whereBetween('created_at', [$start, $end])
                    ->selectRaw('date(created_at) as day')
                    ->selectRaw('sum(total_amount) as revenue_amount')
                    ->groupBy('day')
                    ->pluck('revenue_amount', 'day');

                $series = [];
                $cursor = $start->copy();

                while ($cursor->lte($end)) {
                    $key = $cursor->toDateString();
                    $series[$key] = (int) ($rows[$key] ?? 0);
                    $cursor->addDay();
                }

                return $series;
            },
        );
    }

    /**
     * @return array<string, int> OrderStatus value => count
     */
    public function orderStatusDistribution(DashboardPeriod $period): array
    {
        return Cache::remember(
            "dashboard:status_distribution:{$period->value}",
            self::REPORTING_TTL_SECONDS,
            function () use ($period): array {
                [$start, $end] = $period->range();

                $counts = Order::query()
                    ->whereBetween('created_at', [$start, $end])
                    ->selectRaw('status, count(*) as aggregate')
                    ->groupBy('status')
                    ->pluck('aggregate', 'status');

                return collect(OrderStatus::cases())
                    ->mapWithKeys(fn (OrderStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
                    ->all();
            },
        );
    }

    /**
     * @return array<string, int> PaymentMethod value => count
     */
    public function paymentMethodDistribution(DashboardPeriod $period): array
    {
        return Cache::remember(
            "dashboard:payment_method_distribution:{$period->value}",
            self::REPORTING_TTL_SECONDS,
            function () use ($period): array {
                [$start, $end] = $period->range();

                return Order::query()
                    ->whereBetween('created_at', [$start, $end])
                    ->selectRaw('payment_method, count(*) as aggregate')
                    ->groupBy('payment_method')
                    ->pluck('aggregate', 'payment_method')
                    ->map(fn ($count): int => (int) $count)
                    ->all();
            },
        );
    }

    private function deliveredOrdersQuery(DashboardPeriod $period): Builder
    {
        [$start, $end] = $period->range();

        return Order::query()
            ->where('status', OrderStatus::Delivered)
            ->whereBetween('created_at', [$start, $end]);
    }
}
