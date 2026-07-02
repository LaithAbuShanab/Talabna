<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DataTransferObjects\Order\TransitionOrderStatusData;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Exceptions\OrderStatusTransitionException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Services\OrderStatusTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OrderStatusTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatusTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderStatusTransitionService::class);
    }

    private function makeOrder(OrderStatus $status, DeliveryType $deliveryType = DeliveryType::Pickup): Order
    {
        return Order::factory()->create([
            'status' => $status,
            'delivery_type' => $deliveryType,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // --- Allowed transitions -------------------------------------------------

    public function test_pending_can_be_accepted_by_an_admin(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Accepted,
            actor: $this->admin(),
        ));

        $this->assertSame(OrderStatus::Accepted, $updated->status);
    }

    public function test_pending_can_be_rejected_by_an_admin_with_a_reason(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Rejected,
            actor: $this->admin(),
            reason: 'Out of stock',
        ));

        $this->assertSame(OrderStatus::Rejected, $updated->status);
        $this->assertSame('Out of stock', $updated->rejection_reason);
    }

    public function test_pending_can_be_cancelled_by_the_customer(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'user_id' => $customer->id]);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Cancelled,
            actor: $customer,
            reason: 'Changed my mind',
        ));

        $this->assertSame(OrderStatus::Cancelled, $updated->status);
        $this->assertSame('Changed my mind', $updated->cancellation_reason);
    }

    public function test_accepted_can_move_to_preparing_by_an_admin(): void
    {
        $order = $this->makeOrder(OrderStatus::Accepted);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Preparing,
            actor: $this->admin(),
        ));

        $this->assertSame(OrderStatus::Preparing, $updated->status);
    }

    public function test_accepted_can_be_cancelled_by_the_customer(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Accepted, 'user_id' => $customer->id]);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Cancelled,
            actor: $customer,
            reason: 'Changed my mind',
        ));

        $this->assertSame(OrderStatus::Cancelled, $updated->status);
    }

    public function test_preparing_can_move_to_ready_by_an_admin(): void
    {
        $order = $this->makeOrder(OrderStatus::Preparing);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Ready,
            actor: $this->admin(),
        ));

        $this->assertSame(OrderStatus::Ready, $updated->status);
    }

    public function test_preparing_can_be_cancelled_by_an_admin(): void
    {
        $order = $this->makeOrder(OrderStatus::Preparing);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Cancelled,
            actor: $this->admin(),
            reason: 'Kitchen issue',
        ));

        $this->assertSame(OrderStatus::Cancelled, $updated->status);
    }

    public function test_ready_can_move_to_out_for_delivery_by_an_admin(): void
    {
        $order = $this->makeOrder(OrderStatus::Ready, DeliveryType::Delivery);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::OutForDelivery,
            actor: $this->admin(),
        ));

        $this->assertSame(OrderStatus::OutForDelivery, $updated->status);
    }

    public function test_ready_can_move_directly_to_delivered_for_a_pickup_order(): void
    {
        $order = $this->makeOrder(OrderStatus::Ready, DeliveryType::Pickup);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Delivered,
            actor: $this->admin(),
        ));

        $this->assertSame(OrderStatus::Delivered, $updated->status);
    }

    public function test_ready_can_be_cancelled_by_an_admin_with_special_permission(): void
    {
        $order = $this->makeOrder(OrderStatus::Ready);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Cancelled,
            actor: $this->admin(),
            reason: 'Unable to fulfill',
        ));

        $this->assertSame(OrderStatus::Cancelled, $updated->status);
    }

    public function test_out_for_delivery_can_move_to_delivered_by_an_admin(): void
    {
        $order = $this->makeOrder(OrderStatus::OutForDelivery, DeliveryType::Delivery);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Delivered,
            actor: $this->admin(),
        ));

        $this->assertSame(OrderStatus::Delivered, $updated->status);
    }

    public function test_out_for_delivery_can_be_cancelled_by_an_admin_with_very_special_permission(): void
    {
        $order = $this->makeOrder(OrderStatus::OutForDelivery, DeliveryType::Delivery);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Cancelled,
            actor: $this->admin(),
            reason: 'Driver unable to deliver',
        ));

        $this->assertSame(OrderStatus::Cancelled, $updated->status);
    }

    // --- Disallowed transitions -------------------------------------------------

    /**
     * @return iterable<string, array{OrderStatus, OrderStatus}>
     */
    public static function invalidTransitions(): iterable
    {
        yield 'pending to preparing' => [OrderStatus::Pending, OrderStatus::Preparing];
        yield 'pending to ready' => [OrderStatus::Pending, OrderStatus::Ready];
        yield 'pending to delivered' => [OrderStatus::Pending, OrderStatus::Delivered];
        yield 'accepted to rejected' => [OrderStatus::Accepted, OrderStatus::Rejected];
        yield 'accepted to ready' => [OrderStatus::Accepted, OrderStatus::Ready];
        yield 'accepted to out_for_delivery' => [OrderStatus::Accepted, OrderStatus::OutForDelivery];
        yield 'preparing to accepted' => [OrderStatus::Preparing, OrderStatus::Accepted];
        yield 'preparing to out_for_delivery' => [OrderStatus::Preparing, OrderStatus::OutForDelivery];
        yield 'preparing to delivered' => [OrderStatus::Preparing, OrderStatus::Delivered];
        yield 'ready to preparing' => [OrderStatus::Ready, OrderStatus::Preparing];
        yield 'ready to rejected' => [OrderStatus::Ready, OrderStatus::Rejected];
        yield 'out_for_delivery to ready' => [OrderStatus::OutForDelivery, OrderStatus::Ready];
        yield 'out_for_delivery to preparing' => [OrderStatus::OutForDelivery, OrderStatus::Preparing];
    }

    #[DataProvider('invalidTransitions')]
    public function test_it_rejects_structurally_invalid_transitions(OrderStatus $from, OrderStatus $to): void
    {
        $order = $this->makeOrder($from, DeliveryType::Delivery);

        try {
            $this->service->transition($order, new TransitionOrderStatusData(
                to: $to,
                actor: $this->admin(),
                reason: 'reason', // present so this never fails on reason_required instead
            ));
            $this->fail("Expected transition {$from->value} -> {$to->value} to be rejected.");
        } catch (OrderStatusTransitionException $e) {
            $this->assertSame('invalid_transition', $e->errorCode);
        }
    }

    /**
     * @return iterable<string, array{OrderStatus}>
     */
    public static function terminalStatuses(): iterable
    {
        yield 'delivered' => [OrderStatus::Delivered];
        yield 'cancelled' => [OrderStatus::Cancelled];
        yield 'rejected' => [OrderStatus::Rejected];
    }

    #[DataProvider('terminalStatuses')]
    public function test_a_terminal_order_cannot_be_transitioned_at_all(OrderStatus $status): void
    {
        $order = $this->makeOrder($status);

        try {
            $this->service->transition($order, new TransitionOrderStatusData(
                to: OrderStatus::Accepted,
                actor: $this->admin(),
            ));
            $this->fail('Expected OrderStatusTransitionException was not thrown.');
        } catch (OrderStatusTransitionException $e) {
            $this->assertSame('terminal_state', $e->errorCode);
        }
    }

    public function test_ready_to_delivered_is_rejected_for_a_delivery_order(): void
    {
        $order = $this->makeOrder(OrderStatus::Ready, DeliveryType::Delivery);

        try {
            $this->service->transition($order, new TransitionOrderStatusData(
                to: OrderStatus::Delivered,
                actor: $this->admin(),
            ));
            $this->fail('Expected OrderStatusTransitionException was not thrown.');
        } catch (OrderStatusTransitionException $e) {
            $this->assertSame('pickup_only_transition', $e->errorCode);
        }
    }

    // --- Reason enforcement -------------------------------------------------

    public function test_rejecting_without_a_reason_is_rejected(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        try {
            $this->service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Rejected, actor: $this->admin()));
            $this->fail('Expected OrderStatusTransitionException was not thrown.');
        } catch (OrderStatusTransitionException $e) {
            $this->assertSame('reason_required', $e->errorCode);
        }
    }

    public function test_cancelling_without_a_reason_is_rejected(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        try {
            $this->service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Cancelled, actor: $this->admin()));
            $this->fail('Expected OrderStatusTransitionException was not thrown.');
        } catch (OrderStatusTransitionException $e) {
            $this->assertSame('reason_required', $e->errorCode);
        }
    }

    // --- Authorization: customer -------------------------------------------------

    public function test_customer_cannot_perform_administrative_transitions(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'user_id' => $customer->id]);

        try {
            $this->service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Accepted, actor: $customer));
            $this->fail('Expected OrderStatusTransitionException was not thrown.');
        } catch (OrderStatusTransitionException $e) {
            $this->assertSame('unauthorized_transition', $e->errorCode);
        }
    }

    public function test_customer_cannot_reject_their_own_order(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'user_id' => $customer->id]);

        try {
            $this->service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Rejected, actor: $customer, reason: 'no'));
            $this->fail('Expected OrderStatusTransitionException was not thrown.');
        } catch (OrderStatusTransitionException $e) {
            $this->assertSame('unauthorized_transition', $e->errorCode);
        }
    }

    public function test_customer_cannot_cancel_someone_elses_order(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        try {
            $this->service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Cancelled, actor: $customer, reason: 'not mine'));
            $this->fail('Expected OrderStatusTransitionException was not thrown.');
        } catch (OrderStatusTransitionException $e) {
            $this->assertSame('unauthorized_transition', $e->errorCode);
        }
    }

    public function test_customer_cannot_cancel_once_preparing_has_started(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Preparing, 'user_id' => $customer->id]);

        try {
            $this->service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Cancelled, actor: $customer, reason: 'too late'));
            $this->fail('Expected OrderStatusTransitionException was not thrown.');
        } catch (OrderStatusTransitionException $e) {
            $this->assertSame('unauthorized_transition', $e->errorCode);
        }
    }

    // --- Authorization: special permission gates -------------------------------------------------

    public function test_cancelling_at_ready_stage_is_denied_when_the_policy_denies_it(): void
    {
        $policy = Mockery::mock(OrderPolicy::class);
        $policy->shouldReceive('cancelAtReadyStage')->once()->andReturn(false);
        $this->app->instance(OrderPolicy::class, $policy);
        $service = app(OrderStatusTransitionService::class);

        $order = $this->makeOrder(OrderStatus::Ready);

        try {
            $service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Cancelled, actor: $this->admin(), reason: 'test'));
            $this->fail('Expected OrderStatusTransitionException was not thrown.');
        } catch (OrderStatusTransitionException $e) {
            $this->assertSame('unauthorized_special_permission', $e->errorCode);
        }
    }

    public function test_cancelling_at_out_for_delivery_stage_is_denied_when_the_policy_denies_it(): void
    {
        $policy = Mockery::mock(OrderPolicy::class);
        $policy->shouldReceive('cancelAtOutForDeliveryStage')->once()->andReturn(false);
        $this->app->instance(OrderPolicy::class, $policy);
        $service = app(OrderStatusTransitionService::class);

        $order = $this->makeOrder(OrderStatus::OutForDelivery, DeliveryType::Delivery);

        try {
            $service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Cancelled, actor: $this->admin(), reason: 'test'));
            $this->fail('Expected OrderStatusTransitionException was not thrown.');
        } catch (OrderStatusTransitionException $e) {
            $this->assertSame('unauthorized_special_permission', $e->errorCode);
        }
    }

    public function test_a_null_actor_skips_authorization_for_system_transitions(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Accepted, actor: null));

        $this->assertSame(OrderStatus::Accepted, $updated->status);
    }

    // --- Estimated preparation/delivery time -------------------------------------------------

    public function test_accepting_with_estimated_preparation_minutes_sets_expected_delivery_at(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Accepted,
            actor: $this->admin(),
            estimatedPreparationMinutes: 30,
        ));

        $this->assertNotNull($updated->expected_delivery_at);
        $this->assertEqualsWithDelta(
            now()->addMinutes(30)->timestamp,
            $updated->expected_delivery_at->timestamp,
            2,
        );
    }

    public function test_an_explicit_expected_delivery_at_takes_precedence_over_estimated_minutes(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);
        $explicitTime = now()->addHours(3);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Accepted,
            actor: $this->admin(),
            estimatedPreparationMinutes: 15,
            expectedDeliveryAt: $explicitTime,
        ));

        $this->assertSame($explicitTime->timestamp, $updated->expected_delivery_at->timestamp);
    }

    // --- History recording -------------------------------------------------

    public function test_it_records_from_status_to_status_actor_reason_and_metadata(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder(OrderStatus::Pending);

        $this->service->transition($order, new TransitionOrderStatusData(
            to: OrderStatus::Rejected,
            actor: $admin,
            reason: 'Kitchen closed early',
            metadata: ['sub_reason_code' => 'KITCHEN_CLOSED'],
        ));

        $history = OrderStatusHistory::query()->where('order_id', $order->id)->latest('id')->first();

        $this->assertSame(OrderStatus::Pending, $history->from_status);
        $this->assertSame(OrderStatus::Rejected, $history->status);
        $this->assertSame($admin->id, $history->changed_by_user_id);
        $this->assertSame('Kitchen closed early', $history->note);
        $this->assertSame(['sub_reason_code' => 'KITCHEN_CLOSED'], $history->metadata);
    }

    public function test_a_system_transition_records_a_null_changed_by_user_id(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        $this->service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Accepted, actor: null));

        $history = OrderStatusHistory::query()->where('order_id', $order->id)->latest('id')->first();
        $this->assertNull($history->changed_by_user_id);
    }

    // --- Row locking / freshness -------------------------------------------------

    public function test_it_validates_against_the_current_database_status_not_a_stale_in_memory_copy(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        // Simulate another process having already moved the order on,
        // bypassing our in-memory copy — the service must notice this via
        // its own fresh, locked read rather than trusting $order->status.
        DB::table('orders')->where('id', $order->id)->update(['status' => OrderStatus::Delivered->value]);

        try {
            $this->service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Accepted, actor: $this->admin()));
            $this->fail('Expected OrderStatusTransitionException was not thrown.');
        } catch (OrderStatusTransitionException $e) {
            $this->assertSame('terminal_state', $e->errorCode);
        }
    }

    public function test_it_rolls_back_the_order_update_if_history_creation_fails(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        OrderStatusHistory::creating(function (): never {
            throw new RuntimeException('Simulated failure recording history.');
        });

        try {
            try {
                $this->service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Accepted, actor: $this->admin()));
                $this->fail('Expected RuntimeException was not thrown.');
            } catch (RuntimeException $e) {
                $this->assertSame('Simulated failure recording history.', $e->getMessage());
            }
        } finally {
            OrderStatusHistory::flushEventListeners();
        }

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    // --- Events -------------------------------------------------

    public function test_it_dispatches_order_status_changed_after_a_successful_transition(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $admin = $this->admin();
        $order = $this->makeOrder(OrderStatus::Pending);

        $updated = $this->service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Accepted, actor: $admin));

        Event::assertDispatched(OrderStatusChanged::class, function (OrderStatusChanged $event) use ($updated, $admin): bool {
            return $event->order->is($updated)
                && $event->from === OrderStatus::Pending
                && $event->to === OrderStatus::Accepted
                && $event->actor?->is($admin);
        });
    }

    public function test_it_does_not_dispatch_order_status_changed_when_the_transition_fails(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $order = $this->makeOrder(OrderStatus::Delivered);

        try {
            $this->service->transition($order, new TransitionOrderStatusData(to: OrderStatus::Accepted, actor: $this->admin()));
        } catch (OrderStatusTransitionException) {
            // expected
        }

        Event::assertNotDispatched(OrderStatusChanged::class);
    }
}
