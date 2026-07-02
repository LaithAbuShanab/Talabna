<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Listeners\LogAdminOrderStatusChange;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogAdminOrderStatusChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_when_a_staff_member_changes_an_order_status(): void
    {
        $admin = User::factory()->manager()->create();
        $order = Order::factory()->create();

        (new LogAdminOrderStatusChange(app(AdminActivityLogger::class)))
            ->handle(new OrderStatusChanged($order, OrderStatus::Pending, OrderStatus::Accepted, $admin));

        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $admin->id,
            'action' => 'order.status_changed',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
        ]);
    }

    public function test_does_not_log_a_customer_self_cancellation(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create();

        (new LogAdminOrderStatusChange(app(AdminActivityLogger::class)))
            ->handle(new OrderStatusChanged($order, OrderStatus::Pending, OrderStatus::Cancelled, $customer));

        $this->assertDatabaseMissing('admin_activity_logs', ['action' => 'order.status_changed']);
    }

    public function test_does_not_log_a_null_actor_system_transition(): void
    {
        $order = Order::factory()->create();

        (new LogAdminOrderStatusChange(app(AdminActivityLogger::class)))
            ->handle(new OrderStatusChanged($order, OrderStatus::Pending, OrderStatus::Accepted, null));

        $this->assertDatabaseMissing('admin_activity_logs', ['action' => 'order.status_changed']);
    }
}
