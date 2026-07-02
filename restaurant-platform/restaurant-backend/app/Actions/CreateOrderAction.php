<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\Cart\CartPricingRequestData;
use App\DataTransferObjects\Order\CreateOrderData;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderCreated;
use App\Exceptions\OrderCreationException;
use App\Models\BusinessHour;
use App\Models\CouponUsage;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\RestaurantSetting;
use App\Services\CartPricingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The checkout use case: turns a priced cart into a real, persisted Order.
 * Cart/coupon/product validation and every amount come from
 * CartPricingService — this class never trusts anything the caller says
 * about price, and never recomputes pricing math itself.
 *
 * @see CreateOrderData
 */
final class CreateOrderAction
{
    /** Relations eagerly loaded on the Order this action returns. */
    private const array ORDER_RELATIONS = [
        'items.options',
        'statusHistories',
        'payments',
        'user',
        'customerAddress',
        'coupon',
        'deliveryZone',
    ];

    public function __construct(private readonly CartPricingService $pricingService) {}

    public function execute(CreateOrderData $data): Order
    {
        $existing = $this->findExistingOrder($data);

        if ($existing instanceof Order) {
            return $existing;
        }

        $settings = RestaurantSetting::current();

        if (! $this->isRestaurantOpen($settings)) {
            throw new OrderCreationException('restaurant_closed');
        }

        $address = $this->resolveDeliveryAddress($data);

        // Validates the cart (product/option/zone/coupon/min-order) and
        // computes every amount fresh from the database — see
        // App\Services\CartPricingService. Nothing here is trusted from
        // the caller.
        $pricing = $this->pricingService->price(new CartPricingRequestData(
            items: $data->items,
            deliveryType: $data->deliveryType,
            deliveryZoneId: $data->deliveryZoneId,
            couponCode: $data->couponCode,
            userId: $data->userId,
        ));

        try {
            $order = DB::transaction(function () use ($data, $pricing, $address): Order {
                $order = Order::query()->create([
                    'idempotency_key' => $data->idempotencyKey,
                    'user_id' => $data->userId,
                    'status' => OrderStatus::Pending,
                    'delivery_type' => $data->deliveryType,
                    'payment_method' => $data->paymentMethod,
                    'payment_status' => PaymentStatus::Pending,
                    'subtotal_amount' => $pricing->itemsSubtotalAmount,
                    'discount_amount' => $pricing->discountAmount,
                    'delivery_fee_amount' => $pricing->deliveryFeeAmount,
                    'total_amount' => $pricing->grandTotalAmount,
                    'coupon_id' => $pricing->appliedCouponId,
                    'delivery_zone_id' => $pricing->deliveryZoneId,
                    'customer_address_id' => $address?->id,
                    'delivery_address_line' => $address?->address_line1,
                    'delivery_city' => $address?->city,
                    'delivery_latitude' => $address?->latitude,
                    'delivery_longitude' => $address?->longitude,
                    'customer_notes' => $data->customerNotes,
                ]);

                foreach ($pricing->items as $itemData) {
                    $orderItem = $order->items()->create([
                        'product_id' => $itemData->productId,
                        'product_name' => $itemData->productName,
                        'product_price_amount' => $itemData->unitBasePriceAmount,
                        'quantity' => $itemData->quantity,
                        'unit_total_amount' => $itemData->unitTotalAmount,
                        'line_total_amount' => $itemData->lineTotalAmount,
                    ]);

                    foreach ($itemData->options as $optionData) {
                        $orderItem->options()->create([
                            'option_value_id' => $optionData->optionValueId,
                            'option_group_name' => $optionData->optionGroupName,
                            'option_value_name' => $optionData->optionValueName,
                            'price_delta_amount' => $optionData->priceDeltaAmount,
                        ]);
                    }
                }

                $order->statusHistories()->create([
                    'status' => OrderStatus::Pending,
                ]);

                if ($pricing->appliedCouponId !== null) {
                    CouponUsage::query()->create([
                        'coupon_id' => $pricing->appliedCouponId,
                        'user_id' => $data->userId,
                        'order_id' => $order->id,
                        'discount_amount' => $pricing->discountAmount,
                    ]);
                }

                $order->payments()->create([
                    'method' => $data->paymentMethod,
                    'status' => PaymentStatus::Pending,
                    'amount' => $pricing->grandTotalAmount,
                ]);

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            // A genuine race: two requests with the same idempotency key
            // committed at the same time. The unique index on
            // (user_id, idempotency_key) is the authoritative guard; the
            // findExistingOrder() call above only handles the (far more
            // common) sequential-retry case.
            $existing = $this->findExistingOrder($data);

            if ($existing instanceof Order) {
                return $existing;
            }

            throw $e;
        }

        // Dispatched after the transaction has committed, not from inside
        // it, so a listener can never observe an order that gets rolled
        // back — see App\Events\OrderCreated.
        OrderCreated::dispatch($order);

        return $order->load(self::ORDER_RELATIONS);
    }

    private function findExistingOrder(CreateOrderData $data): ?Order
    {
        return Order::query()
            ->where('user_id', $data->userId)
            ->where('idempotency_key', $data->idempotencyKey)
            ->with(self::ORDER_RELATIONS)
            ->first();
    }

    private function isRestaurantOpen(RestaurantSetting $settings): bool
    {
        if (! $settings->is_accepting_orders) {
            return false;
        }

        $now = now();
        $businessHour = BusinessHour::query()->where('day_of_week', $now->dayOfWeek)->first();

        if (! $businessHour instanceof BusinessHour || $businessHour->is_closed) {
            return false;
        }

        if ($businessHour->opens_at === null || $businessHour->closes_at === null) {
            return false;
        }

        $currentTime = $now->format('H:i:s');

        return $currentTime >= $businessHour->opens_at && $currentTime <= $businessHour->closes_at;
    }

    private function resolveDeliveryAddress(CreateOrderData $data): ?CustomerAddress
    {
        if ($data->deliveryType !== DeliveryType::Delivery) {
            return null;
        }

        if ($data->customerAddressId === null) {
            throw new OrderCreationException('delivery_address_required');
        }

        $address = CustomerAddress::query()->find($data->customerAddressId);

        if (! $address instanceof CustomerAddress || $address->user_id !== $data->userId) {
            throw new OrderCreationException('delivery_address_invalid');
        }

        return $address;
    }
}
