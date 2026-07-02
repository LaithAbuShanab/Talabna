<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Cart;

/**
 * One line of a cart pricing request. Deliberately carries no price of any
 * kind — only what the customer picked (product, quantity, chosen option
 * values). CartPricingService always looks up the current price from the
 * database; there is no field here a client could use to smuggle in a
 * price, by construction.
 */
final readonly class CartItemInputData
{
    /**
     * @param  list<int>  $optionValueIds
     */
    public function __construct(
        public int $productId,
        public int $quantity,
        public array $optionValueIds = [],
    ) {}
}
