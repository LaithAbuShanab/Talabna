<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\SendCustomerPushNotificationJob;

/**
 * Builds the (already-translated, via lang/{locale}/notifications.php)
 * push text and hands off to App\Jobs\SendCustomerPushNotificationJob for
 * actual delivery — this listener itself is not queued: dispatching a
 * queued job is already cheap and synchronous, so queuing the listener too
 * would just be a pointless extra hop. See that Job's docblock for the
 * queuing/retry/idempotency contract, and docs/NOTIFICATIONS.md for the
 * overall architecture.
 */
final class SendOrderCreatedPushNotification
{
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        SendCustomerPushNotificationJob::dispatch(
            userId: $order->user_id,
            title: __('notifications.order_created.title'),
            body: __('notifications.order_created.body', ['number' => $order->order_number]),
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
            ],
            idempotencyKey: sprintf('push:%s:%d', OrderCreated::class, $order->id),
        );
    }
}
