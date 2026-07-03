<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\Orders\OrderResource's list/view access
 * (App\Policies\OrderPolicy), search across order number/customer name/
 * phone, filters, default sort, and that no create/edit route exists at
 * all — see docs/ADMIN_ORDERS.md.
 */
class OrderResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(UserRole $role = UserRole::SuperAdmin): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_every_admin_role_can_view_the_orders_list(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager, UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $this->actingAs($this->admin($role))->get(OrderResource::getUrl('index'))->assertOk();
        }
    }

    public function test_a_customer_cannot_view_the_orders_list(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(OrderResource::getUrl('index'))->assertForbidden();
    }

    public function test_any_admin_role_can_view_any_orders_detail_page(): void
    {
        $order = Order::factory()->create();

        foreach ([UserRole::SuperAdmin, UserRole::Manager, UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $this->actingAs($this->admin($role))->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk();
        }
    }

    public function test_a_customer_cannot_view_someone_elses_order_detail_page(): void
    {
        $stranger = User::factory()->create();
        $order = Order::factory()->create();

        $this->actingAs($stranger)->get(OrderResource::getUrl('view', ['record' => $order]))->assertForbidden();
    }

    public function test_there_is_no_create_or_edit_route(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/orders/create')->assertNotFound();

        $order = Order::factory()->create();
        $this->actingAs($admin)->get("/admin/orders/{$order->id}/edit")->assertNotFound();
    }

    public function test_search_finds_an_order_by_order_number(): void
    {
        $admin = $this->admin();
        $target = Order::factory()->create();
        Order::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->searchTable($target->order_number)
            ->assertCanSeeTableRecords([$target])
            ->assertCountTableRecords(1);
    }

    public function test_search_finds_an_order_by_customer_name(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['name' => 'Zaid Al-Kitchen-Finder']);
        $target = Order::factory()->create(['user_id' => $customer->id]);
        Order::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->searchTable('Kitchen-Finder')
            ->assertCanSeeTableRecords([$target])
            ->assertCountTableRecords(1);
    }

    public function test_search_finds_an_order_by_customer_phone(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['phone' => '+962790011223']);
        $target = Order::factory()->create(['user_id' => $customer->id]);
        Order::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->searchTable('790011223')
            ->assertCanSeeTableRecords([$target])
            ->assertCountTableRecords(1);
    }

    public function test_status_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        $pending = Order::factory()->create(['status' => OrderStatus::Pending]);
        Order::factory()->create(['status' => OrderStatus::Delivered]);

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->filterTable('status', [OrderStatus::Pending->value])
            ->assertCanSeeTableRecords([$pending])
            ->assertCountTableRecords(1);
    }

    public function test_payment_status_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        $paid = Order::factory()->create(['payment_status' => PaymentStatus::Paid]);
        Order::factory()->create(['payment_status' => PaymentStatus::Pending]);

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->filterTable('payment_status', [PaymentStatus::Paid->value])
            ->assertCanSeeTableRecords([$paid])
            ->assertCountTableRecords(1);
    }

    public function test_delivery_type_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        $pickup = Order::factory()->pickup()->create();
        Order::factory()->create(['delivery_type' => DeliveryType::Delivery]);

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->filterTable('delivery_type', DeliveryType::Pickup->value)
            ->assertCanSeeTableRecords([$pickup])
            ->assertCountTableRecords(1);
    }

    public function test_date_placed_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        $today = Order::factory()->create(['created_at' => now()]);
        $old = Order::factory()->create(['created_at' => now()->subDays(10)]);

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->filterTable('placed_between', ['placed_from' => now()->subDay()->toDateString()])
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_the_list_defaults_to_newest_orders_first(): void
    {
        $admin = $this->admin();
        $older = Order::factory()->create(['created_at' => now()->subHour()]);
        $newer = Order::factory()->create(['created_at' => now()]);

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    /**
     * Regression test: the overdue icon column's ->icon() closure once
     * returned Heroicon::OutlinedExclamationTriangle->value (a bare string)
     * instead of the enum case itself, which bypasses Filament's
     * BackedEnum-to-icon-name resolution and throws
     * BladeUI\Icons\Exceptions\SvgNotFound the moment the table actually
     * renders a late row — invisible to every other test in this suite
     * since none of them had a non-terminal order with a past
     * expected_delivery_at, so the icon's "true" branch was never actually
     * rendered until real data hit it live.
     */
    public function test_the_list_renders_without_error_when_an_order_is_overdue(): void
    {
        $admin = $this->admin();
        Order::factory()->create([
            'status' => OrderStatus::Preparing,
            'expected_delivery_at' => now()->subMinutes(30),
        ]);

        Livewire::actingAs($admin)->test(ListOrders::class)->assertOk();
        $this->actingAs($admin)->get(OrderResource::getUrl('index'))->assertOk();
    }
}
