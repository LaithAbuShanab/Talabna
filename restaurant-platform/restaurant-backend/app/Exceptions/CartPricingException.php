<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised by CartPricingService for any cart/coupon/delivery validation
 * failure. The message is translated at throw time via lang/{locale}/cart.php
 * (see docs/DATABASE_SCHEMA.md / docs/API_CONVENTIONS.md), so callers get a
 * clear, human-readable, already-localized message — and $errorCode gives
 * API/UI code a stable, language-independent value to branch on.
 */
final class CartPricingException extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $context
     */
    public function __construct(
        public readonly string $errorCode,
        array $context = [],
    ) {
        parent::__construct(trans("cart.errors.{$errorCode}", $context));
    }
}
