<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Cart;

/**
 * A priced, selected option value — same shape as order_item_options'
 * snapshot columns, since this is exactly what would be snapshotted if
 * this cart became a real order.
 */
final readonly class CartPricedOptionData
{
    public function __construct(
        public int $optionValueId,
        public string $optionGroupName,
        public string $optionValueName,
        public int $priceDeltaAmount,
    ) {}
}
