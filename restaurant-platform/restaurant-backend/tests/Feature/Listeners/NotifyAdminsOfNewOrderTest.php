<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Enums\UserRole;
use App\Events\OrderCreated;
use App\Listeners\NotifyAdminsOfNewOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifyAdminsOfNewOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_active_admin_gets_a_database_notification(): void
    {
        $manager = User::factory()->manager()->create(['is_active' => true]);
        $superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);
        $order = Order::factory()->create();

        (new NotifyAdminsOfNewOrder)->handle(new OrderCreated($order));

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $manager->id, 'notifiable_type' => User::class]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $superAdmin->id, 'notifiable_type' => User::class]);
    }

    public function test_customers_never_receive_the_admin_notification(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $order = Order::factory()->for($customer, 'user')->create();

        (new NotifyAdminsOfNewOrder)->handle(new OrderCreated($order));

        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $customer->id]);
    }

    public function test_a_deactivated_admin_is_skipped(): void
    {
        $inactiveAdmin = User::factory()->manager()->create(['is_active' => false]);
        $order = Order::factory()->create();

        (new NotifyAdminsOfNewOrder)->handle(new OrderCreated($order));

        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $inactiveAdmin->id]);
    }

    public function test_the_notification_includes_the_order_number(): void
    {
        $admin = User::factory()->manager()->create(['is_active' => true]);
        $order = Order::factory()->create();

        (new NotifyAdminsOfNewOrder)->handle(new OrderCreated($order));

        $row = \DB::table('notifications')->where('notifiable_id', $admin->id)->first();
        $this->assertStringContainsString($order->order_number, $row->data);
    }
}
