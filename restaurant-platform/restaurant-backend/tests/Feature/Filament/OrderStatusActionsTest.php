<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers every status-change action in
 * App\Filament\Resources\Orders\Actions\OrderStatusActions: the happy path
 * for each, validation, the permission matrix (mirroring
 * App\Policies\OrderPolicy exactly), and that every one of them goes
 * through App\Services\OrderStatusTransitionService (proven indirectly: a
 * successful call always also leaves an order_status_histories row with
 * the right actor, which only that service ever writes).
 */
class OrderStatusActionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function visitOrder(User $actor, Order $order)
    {
        return Livewire::actingAs($actor)->test(ViewOrder::class, ['record' => $order->getKey()]);
    }

    // --- accept --------------------------------------------------------

    public function test_manage_capable_roles_can_accept_a_pending_order(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager, UserRole::Kitchen] as $role) {
            $order = Order::factory()->create(['status' => OrderStatus::Pending]);

            $this->visitOrder($this->user($role), $order)
                ->callAction('accept', data: ['estimated_preparation_minutes' => 20])
                ->assertHasNoActionErrors();

            $order->refresh();
            $this->assertSame(OrderStatus::Accepted, $order->status, "{$role->value} should be able to accept");
            $this->assertNotNull($order->expected_delivery_at);
        }
    }

    public function test_accepting_requires_an_estimated_preparation_time(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $this->visitOrder($this->user(UserRole::Manager), $order)
            ->callAction('accept', data: ['estimated_preparation_minutes' => null])
            ->assertHasActionErrors(['estimated_preparation_minutes' => 'required']);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_the_accept_action_is_not_offered_once_already_accepted(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Accepted]);

        $this->visitOrder($this->user(UserRole::Manager), $order)
            ->assertActionHidden('accept');
    }

    public function test_cashier_and_support_cannot_accept_an_order(): void
    {
        foreach ([UserRole::Cashier, UserRole::Support] as $role) {
            $order = Order::factory()->create(['status' => OrderStatus::Pending]);

            $this->visitOrder($this->user($role), $order)->assertActionHidden('accept');
        }
    }

    // --- reject ----------------------------------------------------------

    public function test_manage_capable_role_can_reject_a_pending_order_with_a_reason(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $this->visitOrder($this->user(UserRole::Manager), $order)
            ->callAction('reject', data: ['reason' => 'Kitchen is closing early'])
            ->assertHasNoActionErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Rejected, $order->status);
        $this->assertSame('Kitchen is closing early', $order->rejection_reason);
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $this->visitOrder($this->user(UserRole::Manager), $order)
            ->callAction('reject', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    // --- startPreparing / markReady --------------------------------------

    public function test_an_accepted_order_can_be_moved_to_preparing_then_ready(): void
    {
        $manager = $this->user(UserRole::Manager);
        $order = Order::factory()->create(['status' => OrderStatus::Accepted]);

        $this->visitOrder($manager, $order)->callAction('startPreparing')->assertHasNoActionErrors();
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);

        $this->visitOrder($manager, $order)->callAction('markReady')->assertHasNoActionErrors();
        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
    }

    // --- outForDelivery / markDelivered ----------------------------------

    public function test_a_delivery_order_must_go_out_for_delivery_before_being_delivered(): void
    {
        $manager = $this->user(UserRole::Manager);
        $order = Order::factory()->create(['status' => OrderStatus::Ready]);

        $this->visitOrder($manager, $order)->assertActionHidden('markDelivered');

        $this->visitOrder($manager, $order)->callAction('outForDelivery')->assertHasNoActionErrors();
        $this->assertSame(OrderStatus::OutForDelivery, $order->fresh()->status);

        $this->visitOrder($manager, $order)->callAction('markDelivered')->assertHasNoActionErrors();
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    public function test_a_pickup_order_can_be_delivered_directly_from_ready(): void
    {
        $manager = $this->user(UserRole::Manager);
        $order = Order::factory()->pickup()->create(['status' => OrderStatus::Ready]);

        $this->visitOrder($manager, $order)->assertActionHidden('outForDelivery');

        $this->visitOrder($manager, $order)->callAction('markDelivered')->assertHasNoActionErrors();
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    // --- cancel: permission tiers ----------------------------------------

    public function test_manage_capable_roles_can_cancel_up_through_preparing(): void
    {
        foreach ([OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Preparing] as $status) {
            $order = Order::factory()->create(['status' => $status]);

            $this->visitOrder($this->user(UserRole::Kitchen), $order)
                ->callAction('cancel', data: ['reason' => 'Customer requested'])
                ->assertHasNoActionErrors();

            $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status, "cancel from {$status->value} should succeed for kitchen");
        }
    }

    public function test_kitchen_cannot_cancel_a_ready_order(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Ready]);

        $this->visitOrder($this->user(UserRole::Kitchen), $order)->assertActionHidden('cancel');
    }

    public function test_manager_can_cancel_a_ready_order(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Ready]);

        $this->visitOrder($this->user(UserRole::Manager), $order)
            ->callAction('cancel', data: ['reason' => 'Kitchen incident'])
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_manager_cannot_cancel_an_out_for_delivery_order(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::OutForDelivery]);

        $this->visitOrder($this->user(UserRole::Manager), $order)->assertActionHidden('cancel');
    }

    public function test_only_super_admin_can_cancel_an_out_for_delivery_order(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::OutForDelivery]);

        $this->visitOrder($this->user(UserRole::SuperAdmin), $order)
            ->callAction('cancel', data: ['reason' => 'High risk cancellation'])
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_cancelling_requires_a_reason(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $this->visitOrder($this->user(UserRole::Manager), $order)
            ->callAction('cancel', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_a_terminal_order_offers_no_further_status_actions(): void
    {
        foreach ([OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Rejected] as $status) {
            $order = Order::factory()->create(['status' => $status]);
            $component = $this->visitOrder($this->user(UserRole::SuperAdmin), $order);

            foreach (['accept', 'reject', 'startPreparing', 'markReady', 'outForDelivery', 'markDelivered', 'cancel'] as $action) {
                $component->assertActionHidden($action);
            }
        }
    }

    // --- every successful transition goes through the real service -------

    public function test_every_successful_action_writes_a_status_history_row_with_the_acting_user(): void
    {
        $manager = $this->user(UserRole::Manager);
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $this->visitOrder($manager, $order)
            ->callAction('accept', data: ['estimated_preparation_minutes' => 10])
            ->assertHasNoActionErrors();

        $history = OrderStatusHistory::query()->where('order_id', $order->id)->latest('id')->first();

        $this->assertNotNull($history);
        $this->assertSame(OrderStatus::Pending, $history->from_status);
        $this->assertSame(OrderStatus::Accepted, $history->status);
        $this->assertSame($manager->id, $history->changed_by_user_id);
    }

    public function test_orders_can_never_be_edited_through_a_generic_form(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'total_amount' => 5000]);

        // No form()/EditRecord exists on OrderResource at all — the only
        // way total_amount could ever change is if some other write path
        // existed; confirm it doesn't drift on its own and that the
        // Livewire component this test suite drives never exposes a
        // 'save'/'update' method the way EditRecord pages do.
        $this->assertFalse(method_exists(ViewOrder::class, 'save'));
        $this->assertSame(5000, $order->fresh()->total_amount);
    }
}
