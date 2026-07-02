<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

/**
 * Authorization for order status changes, used by
 * App\Services\OrderStatusTransitionService. Kept as named abilities that
 * mirror the domain language exactly ("special permission",
 * "very special permission") rather than one generic check, so each one
 * can be tightened independently later (e.g. if a "manager" role is ever
 * introduced) without touching the transition service itself.
 */
class OrderPolicy
{
    /**
     * Bare ownership check — used by the read-only customer endpoints
     * (show/timeline/reorder-preview/review) in
     * App\Http\Controllers\Api\V1\{OrderController, OrderReviewController}.
     * Deliberately separate from cancelAsCustomer(): a wrong-status
     * cancellation attempt on the customer's *own* order should fail with
     * a specific "can't cancel at this stage" business error, not get
     * conflated with "this isn't your order" (403).
     */
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }

    /**
     * The customer's own, limited cancellation right: their own order,
     * and only before the kitchen has started preparing it.
     */
    public function cancelAsCustomer(User $user, Order $order): bool
    {
        return $user->id === $order->user_id && $order->status->isCustomerCancellable();
    }

    /**
     * The baseline ability to run the restaurant side of the lifecycle:
     * accept, reject, prepare, mark ready, dispatch for delivery, mark
     * delivered, and cancel up through "preparing".
     */
    public function manage(User $user, Order $order): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * "Special permission": cancelling an order that's already "ready" —
     * food may already be made. Currently admin-only, same as manage(),
     * but named and checked separately on purpose (see class docblock).
     */
    public function cancelAtReadyStage(User $user, Order $order): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * "Very special permission": cancelling an order that's already out
     * for delivery — the highest-risk cancellation in the lifecycle.
     * Currently the same admin-only check as the others, kept as its own
     * named ability so it can be restricted further without touching
     * OrderStatusTransitionService.
     */
    public function cancelAtOutForDeliveryStage(User $user, Order $order): bool
    {
        return $user->role === UserRole::Admin;
    }
}
