<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cart Pricing Error Messages
    |--------------------------------------------------------------------------
    |
    | Thrown by App\Exceptions\CartPricingException from
    | App\Services\CartPricingService. Keep these in sync with lang/en/cart.php
    | (same keys, same :placeholders).
    |
    */

    'errors' => [
        'cart_empty' => 'سلة الطلبات فارغة.',
        'product_not_found' => 'أحد المنتجات في السلة غير موجود.',
        'product_unavailable' => 'المنتج ":name" غير متاح حاليًا.',
        'category_inactive' => 'تصنيف المنتج ":name" غير متاح حاليًا.',
        'quantity_invalid' => 'يجب أن تكون كمية ":name" بين :min و :max.',
        'option_value_duplicate' => 'تم اختيار نفس الإضافة أكثر من مرة لـ ":name".',
        'option_value_invalid' => 'أحد الخيارات المختارة غير صالح لـ ":name".',
        'option_group_required' => 'يرجى اختيار :min خيار على الأقل من ":group" لـ ":name".',
        'option_group_max_exceeded' => 'يمكنك اختيار :max خيار كحد أقصى من ":group" لـ ":name".',
        'delivery_zone_required' => 'يرجى اختيار منطقة التوصيل.',
        'delivery_zone_invalid' => 'منطقة التوصيل المختارة غير متاحة.',
        'delivery_not_allowed' => 'طلبات التوصيل غير مقبولة حاليًا.',
        'pickup_not_allowed' => 'طلبات الاستلام غير مقبولة حاليًا.',
        'min_order_not_met' => 'الحد الأدنى لقيمة الطلب هو :amount :currency.',
        'coupon_invalid' => 'كود الخصم ":code" غير صالح.',
        'coupon_expired' => 'كود الخصم ":code" منتهي الصلاحية أو لم يبدأ بعد.',
        'coupon_usage_limit_reached' => 'وصل كود الخصم ":code" إلى الحد الأقصى لعدد مرات الاستخدام.',
        'coupon_per_user_limit_reached' => 'لقد استخدمت كود الخصم ":code" الحد الأقصى المسموح به لكل مستخدم.',
        'coupon_min_order_not_met' => 'كود الخصم ":code" يتطلب حدًا أدنى للطلب قدره :amount :currency.',
        'coupon_not_applicable' => 'كود الخصم ":code" لا ينطبق على أي من المنتجات في سلتك.',
    ],

];
