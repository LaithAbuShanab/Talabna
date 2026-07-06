<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Jobs\SendCustomerPushNotificationJob;

/**
 * See App\Listeners\SendOrderCreatedPushNotification's docblock for why
 * this listener is deliberately not queued itself.
 */
final class SendOrderDeliveredPushNotification
{
    public function handle(OrderDelivered $event): void
    {
        $order = $event->order;

        SendCustomerPushNotificationJob::dispatch(
            userId: $order->user_id,
            title: __('notifications.order_delivered.title'),
            body: __('notifications.order_delivered.body', ['number' => $order->order_number]),
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
            ],
            idempotencyKey: sprintf('push:%s:%d', OrderDelivered::class, $order->id),
        );
    }
}
