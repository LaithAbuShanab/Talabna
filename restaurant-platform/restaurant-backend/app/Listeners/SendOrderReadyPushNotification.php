<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderReady;
use App\Jobs\SendCustomerPushNotificationJob;

/**
 * See App\Listeners\SendOrderCreatedPushNotification's docblock for why
 * this listener is deliberately not queued itself.
 */
final class SendOrderReadyPushNotification
{
    public function handle(OrderReady $event): void
    {
        $order = $event->order;

        SendCustomerPushNotificationJob::dispatch(
            userId: $order->user_id,
            title: __('notifications.order_ready.title'),
            body: __('notifications.order_ready.body', ['number' => $order->order_number]),
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
            ],
            idempotencyKey: sprintf('push:%s:%d', OrderReady::class, $order->id),
        );
    }
}
