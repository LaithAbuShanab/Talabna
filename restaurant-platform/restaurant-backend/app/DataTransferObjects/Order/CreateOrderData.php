<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Order;

use App\DataTransferObjects\Cart\CartItemInputData;
use App\Enums\DeliveryType;
use App\Enums\PaymentMethod;

/**
 * Everything App\Actions\CreateOrderAction needs to place an order. Like
 * CartPricingRequestData, this carries no price of any kind — the order's
 * amounts are always recomputed from the database via CartPricingService,
 * never trusted from the caller.
 */
final readonly class CreateOrderData
{
    /**
     * @param  list<CartItemInputData>  $items
     */
    public function __construct(
        public int $userId,
        public array $items,
        public DeliveryType $deliveryType,
        public PaymentMethod $paymentMethod,
        public string $idempotencyKey,
        public ?int $deliveryZoneId = null,
        public ?int $customerAddressId = null,
        public ?string $couponCode = null,
        public ?string $customerNotes = null,
    ) {}
}
