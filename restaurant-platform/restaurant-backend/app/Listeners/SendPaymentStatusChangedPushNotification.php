<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\PaymentStatus;
use App\Events\PaymentStatusChanged;
use App\Jobs\SendCustomerPushNotificationJob;
use LogicException;

/**
 * See App\Listeners\SendOrderCreatedPushNotification's docblock for why
 * this listener is deliberately not queued itself. Nothing payment-specific
 * (amount, transaction reference, gateway metadata) is ever included in the
 * push title/body/data — "عدم تضمين بيانات حساسة داخل push payload" — only
 * the order id/number and the new status, same shape as every order-status
 * push. `pending` is the initial state, never a real "change" worth
 * notifying about, so it's silently skipped.
 */
final class SendPaymentStatusChangedPushNotification
{
    public function handle(PaymentStatusChanged $event): void
    {
        if ($event->to === PaymentStatus::Pending) {
            return;
        }

        $order = $event->order;

        $key = match ($event->to) {
            PaymentStatus::Paid => 'payment_paid',
            PaymentStatus::Failed => 'payment_failed',
            PaymentStatus::Refunded => 'payment_refunded',
            PaymentStatus::PartiallyRefunded => 'payment_partially_refunded',
            PaymentStatus::Pending => throw new LogicException('Unreachable: handled above.'),
        };

        SendCustomerPushNotificationJob::dispatch(
            userId: $order->user_id,
            title: __("notifications.{$key}.title"),
            body: __("notifications.{$key}.body", ['number' => $order->order_number]),
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => $event->to->value,
            ],
            idempotencyKey: sprintf('push:%s:%d:%s', PaymentStatusChanged::class, $event->payment->id, $event->to->value),
        );
    }
}
