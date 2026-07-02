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
 * can be tightened independently without touching the transition service
 * itself. Now that the admin role space is split into
 * super_admin/manager/kitchen/cashier/support (see docs/ADMIN_PANEL.md),
 * each ability lists exactly which of those may perform it — this is the
 * fine-grained half of "not just hiding buttons": even if a kitchen-role
 * user somehow reached a "cancel out-for-delivery" action, this policy
 * would still refuse it server-side.
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
     * delivered, and cancel up through "preparing". Kitchen staff need
     * this for the day-to-day accept/prepare/ready/dispatch flow; cashier
     * and support do not change order status.
     */
    public function manage(User $user, Order $order): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Manager, UserRole::Kitchen], true);
    }

    /**
     * "Special permission": cancelling an order that's already "ready" —
     * food may already be made, so this is deliberately tighter than
     * manage() and excludes kitchen staff.
     */
    public function cancelAtReadyStage(User $user, Order $order): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Manager], true);
    }

    /**
     * "Very special permission": cancelling an order that's already out
     * for delivery — the highest-risk cancellation in the lifecycle,
     * restricted to super_admin only.
     */
    public function cancelAtOutForDeliveryStage(User $user, Order $order): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }
}
