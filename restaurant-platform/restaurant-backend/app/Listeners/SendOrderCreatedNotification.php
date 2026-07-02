<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\PushNotifier;
use App\Events\OrderCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued so a slow/unavailable push provider never delays the HTTP response
 * that created the order. Depends only on the PushNotifier contract, not a
 * concrete provider — see App\Notifications\Push\LogPushNotifier and
 * AppServiceProvider's binding.
 */
final class SendOrderCreatedNotification implements ShouldQueue
{
    public function __construct(private readonly PushNotifier $notifier) {}

    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        $this->notifier->send(
            user: $order->user,
            title: __('Order received'),
            body: __('Your order :number has been received and is pending confirmation.', ['number' => $order->order_number]),
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
            ],
        );
    }
}
