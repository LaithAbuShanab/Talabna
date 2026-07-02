<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Order Creation & Status Transition Error Messages
    |--------------------------------------------------------------------------
    |
    | Thrown by App\Exceptions\OrderCreationException (from
    | App\Actions\CreateOrderAction), App\Exceptions\OrderStatusTransitionException
    | (from App\Services\OrderStatusTransitionService), and
    | App\Exceptions\OrderReviewException (from
    | App\Http\Controllers\Api\V1\OrderReviewController). Keep in sync with
    | lang/ar/order.php. Cart/coupon/pricing errors use lang/{locale}/cart.php
    | instead — see App\Exceptions\CartPricingException.
    |
    */

    'errors' => [
        'restaurant_closed' => 'The restaurant is currently closed and not accepting orders.',
        'delivery_address_required' => 'Please choose a delivery address.',
        'delivery_address_invalid' => 'The selected delivery address is not valid.',
        'terminal_state' => 'This order is already :status and cannot be changed further.',
        'invalid_transition' => 'An order cannot move from :from to :to.',
        'pickup_only_transition' => 'An order can only be marked delivered directly from "ready" for pickup orders; delivery orders must go through "out_for_delivery" first.',
        'unauthorized_transition' => 'You are not allowed to make this change to the order.',
        'unauthorized_special_permission' => 'This change requires special permission that your account does not have.',
        'reason_required' => 'Please provide a reason for this rejection/cancellation.',
        'order_not_delivered' => 'You can only review an order after it has been delivered.',
        'already_reviewed' => 'You have already submitted a review for this order.',
    ],

];
