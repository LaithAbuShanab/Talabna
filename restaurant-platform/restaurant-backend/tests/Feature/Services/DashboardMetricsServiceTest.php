<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\DashboardPeriod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Covers App\Services\DashboardMetricsService — every aggregation the
 * admin Dashboard widgets read, in isolation from Filament/Livewire. The
 * revenue-recognition rule ("delivered, not paid" — see the service's own
 * docblock) and the "cancelled orders never count as revenue" requirement
 * are both asserted directly here, not just implied by other tests.
 */
class DashboardMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): DashboardMetricsService
    {
        return app(DashboardMetricsService::class);
    }

    public function test_orders_count_counts_every_order_in_the_period_regardless_of_status(): void
    {
        Order::factory()->create(['status' => OrderStatus::Pending, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::Delivered, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::Cancelled, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::Pending, 'created_at' => now()->subDays(10)]);

        $this->assertSame(3, $this->service()->ordersCount(DashboardPeriod::Today));
    }

    public function test_revenue_only_counts_delivered_orders(): void
    {
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total_amount' => 1000, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total_amount' => 2000, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::Pending, 'total_amount' => 5000, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::Cancelled, 'total_amount' => 9999, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::Rejected, 'total_amount' => 9999, 'created_at' => now()]);

        $this->assertSame(3000, $this->service()->revenueAmount(DashboardPeriod::Today));
    }

    public function test_cancelled_orders_are_never_counted_in_revenue_even_if_they_have_a_total_amount(): void
    {
        Order::factory()->create(['status' => OrderStatus::Cancelled, 'total_amount' => 50000, 'created_at' => now()]);

        $this->assertSame(0, $this->service()->revenueAmount(DashboardPeriod::Today));
    }

    public function test_revenue_respects_the_selected_periods_date_range(): void
    {
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total_amount' => 1000, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total_amount' => 2000, 'created_at' => now()->subDays(10)]);

        $this->assertSame(1000, $this->service()->revenueAmount(DashboardPeriod::Today));
        $this->assertSame(3000, $this->service()->revenueAmount(DashboardPeriod::Last30Days));
    }

    public function test_average_order_value_is_the_mean_of_delivered_orders_only(): void
    {
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total_amount' => 1000, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total_amount' => 3000, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::Pending, 'total_amount' => 100000, 'created_at' => now()]);

        $this->assertSame(2000, $this->service()->averageOrderValueAmount(DashboardPeriod::Today));
    }

    public function test_average_order_value_is_zero_with_no_delivered_orders(): void
    {
        Order::factory()->create(['status' => OrderStatus::Pending, 'created_at' => now()]);

        $this->assertSame(0, $this->service()->averageOrderValueAmount(DashboardPeriod::Today));
    }

    public function test_cancelled_orders_count_is_scoped_to_the_period(): void
    {
        Order::factory()->create(['status' => OrderStatus::Cancelled, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::Cancelled, 'created_at' => now()->subDays(10)]);

        $this->assertSame(1, $this->service()->cancelledOrdersCount(DashboardPeriod::Today));
        $this->assertSame(2, $this->service()->cancelledOrdersCount(DashboardPeriod::Last30Days));
    }

    public function test_pending_preparing_and_late_counts_reflect_current_state_not_the_period(): void
    {
        Order::factory()->create(['status' => OrderStatus::Pending]);
        Order::factory()->create(['status' => OrderStatus::Preparing]);
        Order::factory()->create([
            'status' => OrderStatus::Accepted,
            'expected_delivery_at' => now()->subHour(),
        ]);
        Order::factory()->create([
            'status' => OrderStatus::Delivered,
            'expected_delivery_at' => now()->subHour(),
        ]);

        $service = $this->service();
        $this->assertSame(1, $service->pendingOrdersCount());
        $this->assertSame(1, $service->preparingOrdersCount());
        // Only the Accepted order is late — the Delivered one already
        // reached a terminal status, so it's no longer "late."
        $this->assertSame(1, $service->lateOrdersCount());
    }

    public function test_best_selling_products_are_ranked_by_quantity_and_only_count_delivered_orders(): void
    {
        $pizza = Product::factory()->create();
        $salad = Product::factory()->create();
        $notSold = Product::factory()->create();

        $deliveredOrder = Order::factory()->create(['status' => OrderStatus::Delivered, 'created_at' => now()]);
        OrderItem::factory()->create([
            'order_id' => $deliveredOrder->id,
            'product_id' => $pizza->id,
            'product_name' => 'Popular Pizza',
            'quantity' => 5,
            'line_total_amount' => 5000,
        ]);
        OrderItem::factory()->create([
            'order_id' => $deliveredOrder->id,
            'product_id' => $salad->id,
            'product_name' => 'Rare Salad',
            'quantity' => 1,
            'line_total_amount' => 800,
        ]);

        $pendingOrder = Order::factory()->create(['status' => OrderStatus::Pending, 'created_at' => now()]);
        OrderItem::factory()->create([
            'order_id' => $pendingOrder->id,
            'product_id' => $notSold->id,
            'product_name' => 'Not Yet Sold',
            'quantity' => 99,
            'line_total_amount' => 99000,
        ]);

        $results = $this->service()->bestSellingProducts(DashboardPeriod::Today);

        $this->assertCount(2, $results);
        $this->assertSame('Popular Pizza', $results[0]['product_name']);
        $this->assertSame(5, $results[0]['quantity']);
        $this->assertSame(5000, $results[0]['revenue_amount']);
        $this->assertSame('Rare Salad', $results[1]['product_name']);
    }

    public function test_sales_trend_includes_every_day_in_range_even_with_zero_revenue(): void
    {
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total_amount' => 1500, 'created_at' => now()]);

        $trend = $this->service()->salesTrend(7);

        $this->assertCount(7, $trend);
        $this->assertSame(1500, $trend[now()->toDateString()]);
        $this->assertSame(0, $trend[now()->subDays(3)->toDateString()]);
    }

    public function test_order_status_distribution_includes_every_status_even_with_zero_count(): void
    {
        Order::factory()->create(['status' => OrderStatus::Pending, 'created_at' => now()]);

        $distribution = $this->service()->orderStatusDistribution(DashboardPeriod::Today);

        $this->assertSame(1, $distribution[OrderStatus::Pending->value]);
        $this->assertSame(0, $distribution[OrderStatus::Delivered->value]);
        $this->assertCount(8, $distribution);
    }

    public function test_payment_method_distribution_counts_orders_per_method(): void
    {
        Order::factory()->create(['payment_method' => PaymentMethod::CashOnDelivery, 'created_at' => now()]);
        Order::factory()->create(['payment_method' => PaymentMethod::CashOnDelivery, 'created_at' => now()]);
        Order::factory()->create(['payment_method' => PaymentMethod::CardOnDelivery, 'created_at' => now()]);

        $distribution = $this->service()->paymentMethodDistribution(DashboardPeriod::Today);

        $this->assertSame(2, $distribution[PaymentMethod::CashOnDelivery->value]);
        $this->assertSame(1, $distribution[PaymentMethod::CardOnDelivery->value]);
    }

    public function test_revenue_is_cached_and_does_not_reflect_a_change_within_the_ttl(): void
    {
        Cache::flush();
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total_amount' => 1000, 'created_at' => now()]);

        $first = $this->service()->revenueAmount(DashboardPeriod::Today);
        $this->assertSame(1000, $first);

        Order::factory()->create(['status' => OrderStatus::Delivered, 'total_amount' => 5000, 'created_at' => now()]);

        // Still the cached value — a fresh query would now return 6000.
        $this->assertSame(1000, $this->service()->revenueAmount(DashboardPeriod::Today));
    }
}
