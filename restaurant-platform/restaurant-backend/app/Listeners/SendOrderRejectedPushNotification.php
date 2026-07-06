<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderRejected;
use App\Jobs\SendCustomerPushNotificationJob;

/**
 * See App\Listeners\SendOrderCreatedPushNotification's docblock for why
 * this listener is deliberately not queued itself. `rejection_reason` is
 * the restaurant's own stated reason for this specific customer's order
 * (e.g. "out of stock") — not sensitive data, and useful for the customer
 * to see, so it's appended to the body when present.
 */
final class SendOrderRejectedPushNotification
{
    public function handle(OrderRejected $event): void
    {
        $order = $event->order;

        $body = __('notifications.order_rejected.body', ['number' => $order->order_number]);

        if (filled($order->rejection_reason)) {
            $body .= ' '.$order->rejection_reason;
        }

        SendCustomerPushNotificationJob::dispatch(
            userId: $order->user_id,
            title: __('notifications.order_rejected.title'),
            body: $body,
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
            ],
            idempotencyKey: sprintf('push:%s:%d', OrderRejected::class, $order->id),
        );
    }
}
