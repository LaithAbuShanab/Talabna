<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_pending_can_move_to_accepted_rejected_or_cancelled(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Accepted));
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Rejected));
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_pending_cannot_jump_straight_to_delivered(): void
    {
        $this->assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_ready_can_move_to_out_for_delivery_or_directly_to_delivered_for_pickup(): void
    {
        $this->assertTrue(OrderStatus::Ready->canTransitionTo(OrderStatus::OutForDelivery));
        $this->assertTrue(OrderStatus::Ready->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_out_for_delivery_can_be_cancelled_structurally(): void
    {
        // Structurally allowed by the graph; App\Services\OrderStatusTransitionService
        // additionally requires the "very special permission" gate before
        // actually allowing this one — see docs/ORDER_LIFECYCLE.md.
        $this->assertTrue(OrderStatus::OutForDelivery->canTransitionTo(OrderStatus::Cancelled));
        $this->assertTrue(OrderStatus::OutForDelivery->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_customer_cancellable_window(): void
    {
        $this->assertTrue(OrderStatus::Pending->isCustomerCancellable());
        $this->assertTrue(OrderStatus::Accepted->isCustomerCancellable());
        $this->assertFalse(OrderStatus::Preparing->isCustomerCancellable());
        $this->assertFalse(OrderStatus::Ready->isCustomerCancellable());
        $this->assertFalse(OrderStatus::OutForDelivery->isCustomerCancellable());
        $this->assertFalse(OrderStatus::Delivered->isCustomerCancellable());
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
    public function test_terminal_statuses_cannot_transition_anywhere(OrderStatus $status): void
    {
        $this->assertTrue($status->isTerminal());

        foreach (OrderStatus::cases() as $next) {
            $this->assertFalse($status->canTransitionTo($next));
        }
    }

    public function test_non_terminal_statuses_are_not_terminal(): void
    {
        foreach ([OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::OutForDelivery] as $status) {
            $this->assertFalse($status->isTerminal());
        }
    }
}
