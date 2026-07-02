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

    public function test_only_admin_can_manage_an_order(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $customer = $this->makeUser(UserRole::Customer);
        $order = $this->makeOrder(OrderStatus::Pending);

        $this->assertTrue($this->policy->manage($admin, $order));
        $this->assertFalse($this->policy->manage($customer, $order));
    }

    public function test_only_admin_can_cancel_at_ready_stage(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $customer = $this->makeUser(UserRole::Customer);
        $order = $this->makeOrder(OrderStatus::Ready);

        $this->assertTrue($this->policy->cancelAtReadyStage($admin, $order));
        $this->assertFalse($this->policy->cancelAtReadyStage($customer, $order));
    }

    public function test_only_admin_can_cancel_at_out_for_delivery_stage(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $customer = $this->makeUser(UserRole::Customer);
        $order = $this->makeOrder(OrderStatus::OutForDelivery);

        $this->assertTrue($this->policy->cancelAtOutForDeliveryStage($admin, $order));
        $this->assertFalse($this->policy->cancelAtOutForDeliveryStage($customer, $order));
    }
}
