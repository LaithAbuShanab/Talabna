<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by App\Services\OrderStatusTransitionService only after its
 * database transaction has committed successfully, alongside the generic
 * App\Events\OrderStatusChanged — this one exists so a listener that only
 * cares about "rejected" (e.g. App\Listeners\SendOrderRejectedPushNotification)
 * doesn't have to filter a generic event by status itself.
 */
final class OrderRejected
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly ?User $actor,
    ) {}
}
