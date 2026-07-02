<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Cart;

/**
 * A priced cart line — every amount here was computed from the database at
 * pricing time, never from client input.
 */
final readonly class CartPricedItemData
{
    /**
     * @param  list<CartPricedOptionData>  $options
     */
    public function __construct(
        public int $productId,
        public string $productName,
        public int $unitBasePriceAmount,
        public array $options,
        public int $unitOptionsTotalAmount,
        public int $unitTotalAmount,
        public int $quantity,
        public int $lineTotalAmount,
    ) {}
}
