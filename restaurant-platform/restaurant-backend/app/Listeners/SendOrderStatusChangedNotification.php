<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\PushNotifier;
use App\Events\OrderStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued so a slow/unavailable push provider never delays the request that
 * changed the order's status. Depends only on the PushNotifier contract —
 * see App\Notifications\Push\LogPushNotifier and AppServiceProvider's
 * binding.
 */
final class SendOrderStatusChangedNotification implements ShouldQueue
{
    public function __construct(private readonly PushNotifier $notifier) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        $this->notifier->send(
            user: $order->user,
            title: __('Order status updated'),
            body: __('Your order :number is now :status.', [
                'number' => $order->order_number,
                'status' => $event->to->value,
            ]),
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'from' => $event->from->value,
                'to' => $event->to->value,
            ],
        );
    }
}
