<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Jobs\SendCustomerPushNotificationJob;

/**
 * See App\Listeners\SendOrderCreatedPushNotification's docblock for why
 * this listener is deliberately not queued itself. `cancellation_reason`
 * is not sensitive data (it's this customer's own order), so it's appended
 * to the body when present.
 */
final class SendOrderCancelledPushNotification
{
    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;

        $body = __('notifications.order_cancelled.body', ['number' => $order->order_number]);

        if (filled($order->cancellation_reason)) {
            $body .= ' '.$order->cancellation_reason;
        }

        SendCustomerPushNotificationJob::dispatch(
            userId: $order->user_id,
            title: __('notifications.order_cancelled.title'),
            body: $body,
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
            ],
            idempotencyKey: sprintf('push:%s:%d', OrderCancelled::class, $order->id),
        );
    }
}
