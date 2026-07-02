<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\AdminActivityLogger;

/**
 * Records a status change to the admin audit trail only when a staff
 * member (not the customer, not a system/automated transition) made it —
 * see App\Services\AdminActivityLogger and docs/ADMIN_PANEL.md. Customer
 * self-cancellations and null-actor system transitions are ordinary,
 * expected activity, not the kind of "sensitive administrative event"
 * this trail exists for.
 */
final class LogAdminOrderStatusChange
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function handle(OrderStatusChanged $event): void
    {
        $actor = $event->actor;

        if ($actor === null || ! $actor->role->isAdmin()) {
            return;
        }

        $this->logger->log(
            actor: $actor,
            action: 'order.status_changed',
            subject: $event->order,
            description: "Order {$event->order->order_number} moved from {$event->from->value} to {$event->to->value}.",
            metadata: ['from' => $event->from->value, 'to' => $event->to->value],
        );
    }
}
