<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Push & Admin Notification Text
    |--------------------------------------------------------------------------
    |
    | Rendered by App\Listeners\Send*PushNotification (customer push, via
    | App\Jobs\SendCustomerPushNotificationJob) and
    | App\Listeners\NotifyAdminsOfNewOrder (admin database notification).
    | Keep in sync with lang/ar/notifications.php (same keys).
    |
    */

    'order_created' => [
        'title' => 'Order received',
        'body' => 'Your order :number has been received and is pending confirmation.',
    ],
    'order_accepted' => [
        'title' => 'Order accepted',
        'body' => 'Your order :number has been accepted and will be prepared soon.',
    ],
    'order_rejected' => [
        'title' => 'Order rejected',
        'body' => 'Your order :number was rejected.',
    ],
    'order_preparing' => [
        'title' => 'Order in the kitchen',
        'body' => 'Your order :number is now being prepared.',
    ],
    'order_ready' => [
        'title' => 'Order ready',
        'body' => 'Your order :number is ready.',
    ],
    'order_out_for_delivery' => [
        'title' => 'Order out for delivery',
        'body' => 'Your order :number is on its way.',
    ],
    'order_delivered' => [
        'title' => 'Order delivered',
        'body' => 'Your order :number has been delivered. Enjoy!',
    ],
    'order_cancelled' => [
        'title' => 'Order cancelled',
        'body' => 'Your order :number has been cancelled.',
    ],
    'payment_paid' => [
        'title' => 'Payment received',
        'body' => 'Payment for order :number has been received.',
    ],
    'payment_failed' => [
        'title' => 'Payment failed',
        'body' => 'Payment for order :number could not be processed.',
    ],
    'payment_refunded' => [
        'title' => 'Payment refunded',
        'body' => 'Order :number has been refunded.',
    ],
    'payment_partially_refunded' => [
        'title' => 'Partial refund',
        'body' => 'Order :number received a partial refund.',
    ],
    'admin_new_order' => [
        'title' => 'New order received',
        'body' => 'A new order :number has been placed.',
    ],

];
