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
    | Keep in sync with lang/en/notifications.php (same keys).
    |
    */

    'order_created' => [
        'title' => 'تم استلام الطلب',
        'body' => 'تم استلام طلبك :number وهو بانتظار التأكيد.',
    ],
    'order_accepted' => [
        'title' => 'تم قبول الطلب',
        'body' => 'تم قبول طلبك :number وسيتم تحضيره قريبًا.',
    ],
    'order_rejected' => [
        'title' => 'تم رفض الطلب',
        'body' => 'تم رفض طلبك :number.',
    ],
    'order_preparing' => [
        'title' => 'الطلب قيد التحضير',
        'body' => 'يتم الآن تحضير طلبك :number.',
    ],
    'order_ready' => [
        'title' => 'الطلب جاهز',
        'body' => 'طلبك :number جاهز.',
    ],
    'order_out_for_delivery' => [
        'title' => 'الطلب قيد التوصيل',
        'body' => 'طلبك :number في الطريق إليك.',
    ],
    'order_delivered' => [
        'title' => 'تم تسليم الطلب',
        'body' => 'تم تسليم طلبك :number. بالهناء والشفاء!',
    ],
    'order_cancelled' => [
        'title' => 'تم إلغاء الطلب',
        'body' => 'تم إلغاء طلبك :number.',
    ],
    'payment_paid' => [
        'title' => 'تم استلام الدفعة',
        'body' => 'تم استلام دفعة طلبك :number.',
    ],
    'payment_failed' => [
        'title' => 'فشلت عملية الدفع',
        'body' => 'تعذر إتمام عملية الدفع لطلبك :number.',
    ],
    'payment_refunded' => [
        'title' => 'تم استرداد المبلغ',
        'body' => 'تم استرداد قيمة طلبك :number.',
    ],
    'payment_partially_refunded' => [
        'title' => 'استرداد جزئي',
        'body' => 'تم استرداد جزء من قيمة طلبك :number.',
    ],
    'admin_new_order' => [
        'title' => 'طلب جديد',
        'body' => 'تم استلام طلب جديد :number.',
    ],

];
