<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cart Pricing Error Messages
    |--------------------------------------------------------------------------
    |
    | Thrown by App\Exceptions\CartPricingException from
    | App\Services\CartPricingService. Keep these in sync with lang/ar/cart.php
    | (same keys, same :placeholders).
    |
    */

    'errors' => [
        'cart_empty' => 'Your cart is empty.',
        'product_not_found' => 'One of the products in your cart could not be found.',
        'product_unavailable' => '":name" is currently unavailable.',
        'category_inactive' => 'The category for ":name" is currently unavailable.',
        'quantity_invalid' => 'The quantity for ":name" must be between :min and :max.',
        'option_value_duplicate' => 'The same option was selected more than once for ":name".',
        'option_value_invalid' => 'One of the selected options is not valid for ":name".',
        'option_group_required' => 'Please select at least :min option(s) from ":group" for ":name".',
        'option_group_max_exceeded' => 'You can select at most :max option(s) from ":group" for ":name".',
        'delivery_zone_required' => 'Please choose a delivery zone.',
        'delivery_zone_invalid' => 'The selected delivery zone is not available.',
        'min_order_not_met' => 'The minimum order amount is :amount :currency.',
        'coupon_invalid' => 'The coupon code ":code" is not valid.',
        'coupon_expired' => 'The coupon code ":code" has expired or is not active yet.',
        'coupon_usage_limit_reached' => 'The coupon code ":code" has reached its usage limit.',
        'coupon_per_user_limit_reached' => 'You have already used the coupon code ":code" the maximum number of times.',
        'coupon_min_order_not_met' => 'The coupon code ":code" requires a minimum order of :amount :currency.',
        'coupon_not_applicable' => 'The coupon code ":code" does not apply to any of the items in your cart.',
    ],

];
