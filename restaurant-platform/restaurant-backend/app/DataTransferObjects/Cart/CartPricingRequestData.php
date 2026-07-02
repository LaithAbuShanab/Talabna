<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Cart;

use App\Enums\DeliveryType;

/**
 * Everything CartPricingService needs to price a cart. No price/total
 * field exists anywhere on this DTO or its items — see CartItemInputData.
 */
final readonly class CartPricingRequestData
{
    /**
     * @param  list<CartItemInputData>  $items
     */
    public function __construct(
        public array $items,
        public DeliveryType $deliveryType,
        public ?int $deliveryZoneId = null,
        public ?string $couponCode = null,
        public ?int $userId = null,
    ) {}
}
