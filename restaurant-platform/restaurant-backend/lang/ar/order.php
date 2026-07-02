<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Order Creation & Status Transition Error Messages
    |--------------------------------------------------------------------------
    |
    | Thrown by App\Exceptions\OrderCreationException (from
    | App\Actions\CreateOrderAction) and App\Exceptions\OrderStatusTransitionException
    | (from App\Services\OrderStatusTransitionService). Keep in sync with
    | lang/en/order.php. Cart/coupon/pricing errors use lang/{locale}/cart.php
    | instead — see App\Exceptions\CartPricingException.
    |
    */

    'errors' => [
        'restaurant_closed' => 'المطعم مغلق حاليًا ولا يستقبل طلبات جديدة.',
        'delivery_address_required' => 'يرجى اختيار عنوان للتوصيل.',
        'delivery_address_invalid' => 'عنوان التوصيل المختار غير صالح.',
        'terminal_state' => 'هذا الطلب بالفعل بحالة :status ولا يمكن تغييره بعد الآن.',
        'invalid_transition' => 'لا يمكن نقل الطلب من الحالة :from إلى الحالة :to.',
        'pickup_only_transition' => 'لا يمكن تحديد الطلب كـ"تم التسليم" مباشرة من حالة "جاهز" إلا لطلبات الاستلام من المطعم؛ طلبات التوصيل يجب أن تمر بحالة "قيد التوصيل" أولًا.',
        'unauthorized_transition' => 'لا تملك صلاحية تنفيذ هذا التغيير على الطلب.',
        'unauthorized_special_permission' => 'يتطلب هذا التغيير صلاحية خاصة لا يملكها حسابك.',
        'reason_required' => 'يرجى إدخال سبب للرفض أو الإلغاء.',
    ],

];
