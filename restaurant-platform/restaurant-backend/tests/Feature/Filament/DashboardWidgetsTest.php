<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DashboardPeriod;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Widgets\BestSellingProductsWidget;
use App\Filament\Widgets\LatestOrdersWidget;
use App\Filament\Widgets\OperationalStatusWidget;
use App\Filament\Widgets\OrdersOverviewWidget;
use App\Filament\Widgets\OrderStatusDistributionWidget;
use App\Filament\Widgets\PaymentMethodDistributionWidget;
use App\Filament\Widgets\SalesTrendChartWidget;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers "احترام صلاحيات الأدوار" (respect role permissions) for the
 * Dashboard: financial widgets (OrdersOverview, SalesTrendChart,
 * PaymentMethodDistribution, BestSellingProducts) are super_admin/manager/
 * cashier only; operational widgets (OperationalStatus,
 * OrderStatusDistribution, LatestOrders) are visible to every admin role,
 * matching App\Policies\OrderPolicy::viewAny()'s tier for the Orders
 * screen itself. Also covers the Dashboard page loading for every role and
 * the exact widget-value correctness for a couple of representative cases
 * (full calculation correctness lives in DashboardMetricsServiceTest).
 */
class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private const array FINANCIAL_WIDGETS = [
        OrdersOverviewWidget::class,
        SalesTrendChartWidget::class,
        PaymentMethodDistributionWidget::class,
        BestSellingProductsWidget::class,
    ];

    private const array OPERATIONAL_WIDGETS = [
        OperationalStatusWidget::class,
        OrderStatusDistributionWidget::class,
        LatestOrdersWidget::class,
    ];

    private function admin(UserRole $role = UserRole::SuperAdmin): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_every_admin_role_can_load_the_dashboard(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager, UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $this->actingAs($this->admin($role))->get('/admin')->assertOk();
        }
    }

    public function test_a_customer_cannot_load_the_dashboard(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_financial_widgets_are_visible_to_super_admin_manager_and_cashier(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager, UserRole::Cashier] as $role) {
            $this->actingAs($this->admin($role));

            foreach (self::FINANCIAL_WIDGETS as $widget) {
                $this->assertTrue($widget::canView(), "{$widget} should be visible to {$role->value}");
            }
        }
    }

    public function test_financial_widgets_are_hidden_from_kitchen_and_support(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Support] as $role) {
            $this->actingAs($this->admin($role));

            foreach (self::FINANCIAL_WIDGETS as $widget) {
                $this->assertFalse($widget::canView(), "{$widget} should be hidden from {$role->value}");
            }
        }
    }

    public function test_operational_widgets_are_visible_to_every_admin_role(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager, UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $this->actingAs($this->admin($role));

            foreach (self::OPERATIONAL_WIDGETS as $widget) {
                $this->assertTrue($widget::canView(), "{$widget} should be visible to {$role->value}");
            }
        }
    }

    public function test_every_widget_renders_without_error(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);
        OrderItem::factory()->create(['order_id' => $order->id]);

        foreach ([...self::FINANCIAL_WIDGETS, ...self::OPERATIONAL_WIDGETS] as $widget) {
            Livewire::actingAs($admin)->test($widget)->assertOk();
        }
    }

    public function test_orders_overview_stat_values_reflect_the_selected_period(): void
    {
        $admin = $this->admin();
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total_amount' => 4000, 'created_at' => now()]);
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total_amount' => 4000, 'created_at' => now()->subDays(10)]);

        Livewire::actingAs($admin)
            ->test(OrdersOverviewWidget::class, ['pageFilters' => ['period' => DashboardPeriod::Today->value]])
            ->assertSee('4.000');

        Livewire::actingAs($admin)
            ->test(OrdersOverviewWidget::class, ['pageFilters' => ['period' => DashboardPeriod::Last30Days->value]])
            ->assertSee('8.000');
    }

    public function test_orders_overview_accepts_the_period_filter_as_a_raw_enum_instance_too(): void
    {
        $admin = $this->admin();
        Order::factory()->create(['status' => OrderStatus::Delivered, 'total_amount' => 4000, 'created_at' => now()]);

        Livewire::actingAs($admin)
            ->test(OrdersOverviewWidget::class, ['pageFilters' => ['period' => DashboardPeriod::Today]])
            ->assertOk()
            ->assertSee('4.000');
    }
}
