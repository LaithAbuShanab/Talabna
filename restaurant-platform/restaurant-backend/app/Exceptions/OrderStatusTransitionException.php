<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised by App\Services\OrderStatusTransitionService for any invalid or
 * unauthorized order status change. Kept separate from
 * OrderCreationException/CartPricingException so each domain's error
 * taxonomy stays small — but shares lang/{locale}/order.php's 'errors' key
 * since both are "order" domain concerns.
 */
final class OrderStatusTransitionException extends RuntimeException
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
