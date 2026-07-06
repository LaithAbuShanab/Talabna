<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Cart;

use App\Enums\DeliveryType;

/**
 * The full, deterministic result of CartPricingService::price(). Every
 * amount is an integer in the smallest currency unit (see currencyCode).
 * grandTotalAmount = itemsSubtotalAmount - discountAmount +
 * deliveryFeeAmount + taxAmount.
 */
final readonly class CartPricingResultData
{
    /**
     * @param  list<CartPricedItemData>  $items
     */
    public function __construct(
        public array $items,
        public string $currencyCode,
        public int $itemsSubtotalAmount,
        public int $optionsTotalAmount,
        public ?int $appliedCouponId,
        public ?string $appliedCouponCode,
        public int $discountAmount,
        public DeliveryType $deliveryType,
        public ?int $deliveryZoneId,
        public int $deliveryFeeAmount,
        public bool $isTaxApplied,
        public bool $isTaxInclusive,
        public int $taxAmount,
        public int $grandTotalAmount,
    ) {}
}
