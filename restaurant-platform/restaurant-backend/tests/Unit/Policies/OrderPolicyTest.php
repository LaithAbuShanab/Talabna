<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use Tests\TestCase;

/**
 * Pure unit test: every model here is an in-memory instance that's never
 * saved, and OrderPolicy never queries the database itself — it only reads
 * attributes already on the objects it's given. No RefreshDatabase needed.
 */
class OrderPolicyTest extends TestCase
{
    private OrderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new OrderPolicy;
    }

    private function makeUser(UserRole $role, int $id = 1): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = $role;

        return $user;
    }

    private function makeOrder(OrderStatus $status, int $userId = 1): Order
    {
        $order = new Order;
        $order->id = 1;
        $order->user_id = $userId;
        $order->status = $status;

        return $order;
    }

    public function test_customer_can_cancel_their_own_pending_order(): void
    {
        $customer = $this->makeUser(UserRole::Customer, id: 5);
        $order = $this->makeOrder(OrderStatus::Pending, userId: 5);

        $this->assertTrue($this->policy->cancelAsCustomer($customer, $order));
    }

    public function test_customer_can_cancel_their_own_accepted_order(): void
    {
        $customer = $this->makeUser(UserRole::Customer, id: 5);
        $order = $this->makeOrder(OrderStatus::Accepted, userId: 5);

        $this->assertTrue($this->policy->cancelAsCustomer($customer, $order));
    }

    public function test_customer_cannot_cancel_once_preparing_has_started(): void
    {
        $customer = $this->makeUser(UserRole::Customer, id: 5);
        $order = $this->makeOrder(OrderStatus::Preparing, userId: 5);

        $this->assertFalse($this->policy->cancelAsCustomer($customer, $order));
    }

    public function test_customer_cannot_cancel_someone_elses_order(): void
    {
        $customer = $this->makeUser(UserRole::Customer, id: 5);
        $order = $this->makeOrder(OrderStatus::Pending, userId: 999);

        $this->assertFalse($this->policy->cancelAsCustomer($customer, $order));
    }

    public function test_super_admin_manager_and_kitchen_can_manage_an_order(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        foreach ([UserRole::SuperAdmin, UserRole::Manager, UserRole::Kitchen] as $role) {
            $this->assertTrue($this->policy->manage($this->makeUser($role), $order), "{$role->value} should be able to manage an order");
        }
    }

    public function test_cashier_support_and_customer_cannot_manage_an_order(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        foreach ([UserRole::Cashier, UserRole::Support, UserRole::Customer] as $role) {
            $this->assertFalse($this->policy->manage($this->makeUser($role), $order), "{$role->value} should not be able to manage an order");
        }
    }

    public function test_only_super_admin_and_manager_can_cancel_at_ready_stage(): void
    {
        $order = $this->makeOrder(OrderStatus::Ready);

        $this->assertTrue($this->policy->cancelAtReadyStage($this->makeUser(UserRole::SuperAdmin), $order));
        $this->assertTrue($this->policy->cancelAtReadyStage($this->makeUser(UserRole::Manager), $order));

        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support, UserRole::Customer] as $role) {
            $this->assertFalse($this->policy->cancelAtReadyStage($this->makeUser($role), $order), "{$role->value} should not be able to cancel at the ready stage");
        }
    }

    public function test_only_super_admin_can_cancel_at_out_for_delivery_stage(): void
    {
        $order = $this->makeOrder(OrderStatus::OutForDelivery);

        $this->assertTrue($this->policy->cancelAtOutForDeliveryStage($this->makeUser(UserRole::SuperAdmin), $order));

        foreach ([UserRole::Manager, UserRole::Kitchen, UserRole::Cashier, UserRole::Support, UserRole::Customer] as $role) {
            $this->assertFalse($this->policy->cancelAtOutForDeliveryStage($this->makeUser($role), $order), "{$role->value} should not be able to cancel at the out-for-delivery stage");
        }
    }
}
