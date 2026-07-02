<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised by App\Actions\CreateOrderAction for order-level validation
 * failures that App\Services\CartPricingService doesn't know about
 * (restaurant open/closed, delivery address). Cart/coupon/pricing failures
 * surface as CartPricingException instead — the two are kept separate on
 * purpose so each error taxonomy stays small and unambiguous.
 */
final class OrderCreationException extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $context
     */
    public function __construct(
        public readonly string $errorCode,
        array $context = [],
    ) {
        parent::__construct(trans("order.errors.{$errorCode}", $context));
    }
}
