<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised by App\Http\Controllers\Api\V1\OrderReviewController for a
 * business-rule failure when submitting a post-delivery rating (order not
 * yet delivered, or already reviewed). Kept separate from
 * OrderStatusTransitionException since reviewing isn't a status change —
 * shares lang/{locale}/order.php's 'errors' key, same as the other order
 * domain exceptions.
 */
final class OrderReviewException extends RuntimeException
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
