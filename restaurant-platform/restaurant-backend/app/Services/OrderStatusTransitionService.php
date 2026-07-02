<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\Order\TransitionOrderStatusData;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusChanged;
use App\Exceptions\OrderStatusTransitionException;
use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use Illuminate\Support\Facades\DB;

/**
 * The single, centralized place an order's status is ever changed after
 * creation. Every rule from docs/ORDER_LIFECYCLE.md is enforced here, not
 * scattered across controllers/Filament actions — see that document for
 * the full lifecycle graph and the reasoning behind each rule.
 */
final class OrderStatusTransitionService
{
    public function __construct(private readonly OrderPolicy $policy) {}

    public function transition(Order $order, TransitionOrderStatusData $data): Order
    {
        $from = null;

        $updatedOrder = DB::transaction(function () use ($order, $data, &$from): Order {
            // Row lock: re-fetch the authoritative current state under
            // SELECT ... FOR UPDATE so two concurrent transition attempts
            // on the same order can never both succeed against a stale
            // status — the second one blocks until the first commits, then
            // sees the already-updated status and is validated against it.
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $from = $locked->status;
            $to = $data->to;

            if ($from->isTerminal()) {
                throw new OrderStatusTransitionException('terminal_state', ['status' => $from->value]);
            }

            if (! $from->canTransitionTo($to)) {
                throw new OrderStatusTransitionException('invalid_transition', [
                    'from' => $from->value,
                    'to' => $to->value,
                ]);
            }

            if ($from === OrderStatus::Ready && $to === OrderStatus::Delivered && $locked->delivery_type !== DeliveryType::Pickup) {
                throw new OrderStatusTransitionException('pickup_only_transition');
            }

            $this->authorize($locked, $to, $data->actor);

            if (in_array($to, [OrderStatus::Rejected, OrderStatus::Cancelled], true) && trim((string) $data->reason) === '') {
                throw new OrderStatusTransitionException('reason_required');
            }

            $locked->status = $to;

            if ($to === OrderStatus::Accepted) {
                $locked->expected_delivery_at = $data->expectedDeliveryAt
                    ?? ($data->estimatedPreparationMinutes !== null
                        ? now()->addMinutes($data->estimatedPreparationMinutes)
                        : $locked->expected_delivery_at);
            }

            if ($to === OrderStatus::Rejected) {
                $locked->rejection_reason = $data->reason;
            }

            if ($to === OrderStatus::Cancelled) {
                $locked->cancellation_reason = $data->reason;
            }

            $locked->save();

            $locked->statusHistories()->create([
                'from_status' => $from,
                'status' => $to,
                'note' => $data->reason,
                'metadata' => $data->metadata === [] ? null : $data->metadata,
                'changed_by_user_id' => $data->actor?->id,
            ]);

            return $locked;
        });

        // Dispatched after the transaction has committed, not from inside
        // it, so a listener can never observe a transition that gets
        // rolled back — see App\Events\OrderStatusChanged.
        OrderStatusChanged::dispatch($updatedOrder, $from, $data->to, $data->actor);

        return $updatedOrder->load(['statusHistories', 'payments']);
    }

    private function authorize(Order $order, OrderStatus $to, ?User $actor): void
    {
        if ($actor === null) {
            // System/automated transition — no user-level check.
            return;
        }

        $isCancellation = $to === OrderStatus::Cancelled;

        if ($actor->role === UserRole::Customer) {
            if (! $isCancellation || ! $this->policy->cancelAsCustomer($actor, $order)) {
                throw new OrderStatusTransitionException('unauthorized_transition');
            }

            return;
        }

        if ($isCancellation && $order->status === OrderStatus::Ready) {
            if (! $this->policy->cancelAtReadyStage($actor, $order)) {
                throw new OrderStatusTransitionException('unauthorized_special_permission');
            }

            return;
        }

        if ($isCancellation && $order->status === OrderStatus::OutForDelivery) {
            if (! $this->policy->cancelAtOutForDeliveryStage($actor, $order)) {
                throw new OrderStatusTransitionException('unauthorized_special_permission');
            }

            return;
        }

        if (! $this->policy->manage($actor, $order)) {
            throw new OrderStatusTransitionException('unauthorized_transition');
        }
    }
}
