<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\Cart\CartItemInputData;
use App\DataTransferObjects\Cart\CartPricedItemData;
use App\DataTransferObjects\Cart\CartPricedOptionData;
use App\DataTransferObjects\Cart\CartPricingRequestData;
use App\DataTransferObjects\Cart\CartPricingResultData;
use App\Enums\CouponType;
use App\Enums\DeliveryType;
use App\Enums\OptionSelectionType;
use App\Exceptions\CartPricingException;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\DeliveryZone;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\RestaurantSetting;
use Illuminate\Support\Collection;

/**
 * Prices a cart from scratch, purely from what's currently in the database.
 * No price/total ever comes from the caller — CartItemInputData only
 * carries product/option/quantity identifiers, never money. This class
 * does not persist anything (no Order is created here); it only computes
 * and validates. See docs/DATABASE_SCHEMA.md and docs/API_CONVENTIONS.md.
 *
 * Validation is fail-fast: the first violation found throws
 * CartPricingException immediately, in the order the checks are written
 * below (cart not empty -> per-item product/options/quantity -> delivery ->
 * order minimum -> coupon). This keeps each failure mode independently
 * testable and the error always unambiguous.
 */
final class CartPricingService
{
    private const int MIN_QUANTITY_PER_ITEM = 1;

    private const int MAX_QUANTITY_PER_ITEM = 50;

    public function price(CartPricingRequestData $request): CartPricingResultData
    {
        if ($request->items === []) {
            throw new CartPricingException('cart_empty');
        }

        $settings = RestaurantSetting::current();

        [$pricedItems, $itemsSubtotal, $optionsTotal] = $this->priceItems($request->items);

        $deliveryZone = $this->resolveDeliveryZone($request->deliveryType, $request->deliveryZoneId);
        $deliveryFee = $request->deliveryType === DeliveryType::Delivery
            ? ($deliveryZone?->delivery_fee_amount ?? $settings->default_delivery_fee_amount)
            : 0;

        $this->assertMinimumOrderMet($itemsSubtotal, $settings, $deliveryZone);

        [$discount, $appliedCouponId, $appliedCouponCode] = $request->couponCode !== null
            ? $this->applyCoupon($request->couponCode, $request->userId, $pricedItems, $itemsSubtotal)
            : [0, null, null];

        $taxableAmount = $itemsSubtotal - $discount;
        $taxAmount = $settings->is_tax_enabled
            ? (int) round($taxableAmount * $settings->tax_rate_bps / 10000)
            : 0;

        $grandTotal = $itemsSubtotal - $discount + $deliveryFee + $taxAmount;

        return new CartPricingResultData(
            items: $pricedItems,
            currencyCode: $settings->currency_code,
            itemsSubtotalAmount: $itemsSubtotal,
            optionsTotalAmount: $optionsTotal,
            appliedCouponId: $appliedCouponId,
            appliedCouponCode: $appliedCouponCode,
            discountAmount: $discount,
            deliveryType: $request->deliveryType,
            deliveryZoneId: $deliveryZone?->id,
            deliveryFeeAmount: $deliveryFee,
            isTaxApplied: $settings->is_tax_enabled,
            taxAmount: $taxAmount,
            grandTotalAmount: $grandTotal,
        );
    }

    /**
     * @param  list<CartItemInputData>  $items
     * @return array{0: list<CartPricedItemData>, 1: int, 2: int}
     */
    private function priceItems(array $items): array
    {
        $pricedItems = [];
        $itemsSubtotal = 0;
        $optionsTotal = 0;

        foreach ($items as $item) {
            $priced = $this->priceItem($item);
            $pricedItems[] = $priced;
            $itemsSubtotal += $priced->lineTotalAmount;
            $optionsTotal += $priced->unitOptionsTotalAmount * $priced->quantity;
        }

        return [$pricedItems, $itemsSubtotal, $optionsTotal];
    }

    private function priceItem(CartItemInputData $item): CartPricedItemData
    {
        if ($item->quantity < self::MIN_QUANTITY_PER_ITEM || $item->quantity > self::MAX_QUANTITY_PER_ITEM) {
            throw new CartPricingException('quantity_invalid', [
                'name' => "product #{$item->productId}",
                'min' => self::MIN_QUANTITY_PER_ITEM,
                'max' => self::MAX_QUANTITY_PER_ITEM,
            ]);
        }

        $product = Product::query()
            ->with(['category', 'optionGroups.values'])
            ->find($item->productId);

        if (! $product instanceof Product) {
            throw new CartPricingException('product_not_found');
        }

        if (! $product->is_active || ! $product->is_available) {
            throw new CartPricingException('product_unavailable', ['name' => $product->name]);
        }

        if (! $product->category?->is_active) {
            throw new CartPricingException('category_inactive', ['name' => $product->name]);
        }

        if (count($item->optionValueIds) !== count(array_unique($item->optionValueIds))) {
            throw new CartPricingException('option_value_duplicate', ['name' => $product->name]);
        }

        $selectedValues = $this->resolveSelectedOptionValues($product, $item->optionValueIds);
        $this->assertOptionGroupSelections($product, $selectedValues);

        $optionsData = $selectedValues->map(fn (OptionValue $value) => new CartPricedOptionData(
            optionValueId: $value->id,
            optionGroupName: $value->optionGroup->name,
            optionValueName: $value->name,
            priceDeltaAmount: $value->price_delta_amount,
        ))->values()->all();

        $unitOptionsTotal = $selectedValues->sum('price_delta_amount');
        $unitTotal = $product->price_amount + $unitOptionsTotal;
        $lineTotal = $unitTotal * $item->quantity;

        return new CartPricedItemData(
            productId: $product->id,
            productName: $product->name,
            categoryId: $product->category_id,
            unitBasePriceAmount: $product->price_amount,
            options: $optionsData,
            unitOptionsTotalAmount: $unitOptionsTotal,
            unitTotalAmount: $unitTotal,
            quantity: $item->quantity,
            lineTotalAmount: $lineTotal,
        );
    }

    /**
     * @param  list<int>  $optionValueIds
     * @return Collection<int, OptionValue>
     */
    private function resolveSelectedOptionValues(Product $product, array $optionValueIds): Collection
    {
        $availableValues = $product->optionGroups
            ->flatMap(fn ($group) => $group->values->each(fn (OptionValue $value) => $value->setRelation('optionGroup', $group)))
            ->keyBy('id');

        return collect($optionValueIds)->map(function (int $id) use ($availableValues, $product) {
            $value = $availableValues->get($id);

            if (! $value instanceof OptionValue || ! $value->is_active) {
                throw new CartPricingException('option_value_invalid', ['name' => $product->name]);
            }

            return $value;
        });
    }

    /**
     * @param  Collection<int, OptionValue>  $selectedValues
     */
    private function assertOptionGroupSelections(Product $product, Collection $selectedValues): void
    {
        $selectedCountsByGroup = $selectedValues->countBy('option_group_id');

        foreach ($product->optionGroups as $group) {
            $pivot = $group->pivot;
            $selectedCount = $selectedCountsByGroup->get($group->id, 0);

            $effectiveMin = $pivot->min_select ?? ($pivot->is_required ? 1 : 0);
            $effectiveMax = $pivot->max_select ?? ($group->selection_type === OptionSelectionType::Single ? 1 : PHP_INT_MAX);

            if ($selectedCount < $effectiveMin) {
                throw new CartPricingException('option_group_required', [
                    'group' => $group->name,
                    'name' => $product->name,
                    'min' => $effectiveMin,
                ]);
            }

            if ($selectedCount > $effectiveMax) {
                throw new CartPricingException('option_group_max_exceeded', [
                    'group' => $group->name,
                    'name' => $product->name,
                    'max' => $effectiveMax,
                ]);
            }
        }
    }

    private function resolveDeliveryZone(DeliveryType $deliveryType, ?int $deliveryZoneId): ?DeliveryZone
    {
        if ($deliveryType !== DeliveryType::Delivery) {
            return null;
        }

        if ($deliveryZoneId === null) {
            throw new CartPricingException('delivery_zone_required');
        }

        $zone = DeliveryZone::query()->find($deliveryZoneId);

        if (! $zone instanceof DeliveryZone || ! $zone->is_active) {
            throw new CartPricingException('delivery_zone_invalid');
        }

        return $zone;
    }

    private function assertMinimumOrderMet(int $itemsSubtotal, RestaurantSetting $settings, ?DeliveryZone $zone): void
    {
        $requiredMinimum = max($settings->min_order_amount, $zone?->min_order_amount ?? 0);

        if ($itemsSubtotal < $requiredMinimum) {
            throw new CartPricingException('min_order_not_met', [
                'amount' => $requiredMinimum,
                'currency' => $settings->currency_code,
            ]);
        }
    }

    /**
     * @param  list<CartPricedItemData>  $pricedItems
     * @return array{0: int, 1: int, 2: string}
     */
    private function applyCoupon(string $couponCode, ?int $userId, array $pricedItems, int $itemsSubtotal): array
    {
        $coupon = Coupon::query()->where('code', strtoupper($couponCode))->first();

        if (! $coupon instanceof Coupon || ! $coupon->is_active) {
            throw new CartPricingException('coupon_invalid', ['code' => $couponCode]);
        }

        $now = now();

        if (($coupon->starts_at !== null && $now->lt($coupon->starts_at))
            || ($coupon->expires_at !== null && $now->gt($coupon->expires_at))) {
            throw new CartPricingException('coupon_expired', ['code' => $couponCode]);
        }

        if ($coupon->usage_limit !== null
            && CouponUsage::query()->where('coupon_id', $coupon->id)->count() >= $coupon->usage_limit) {
            throw new CartPricingException('coupon_usage_limit_reached', ['code' => $couponCode]);
        }

        if ($userId !== null && $coupon->per_user_limit !== null) {
            $userUsageCount = CouponUsage::query()
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();

            if ($userUsageCount >= $coupon->per_user_limit) {
                throw new CartPricingException('coupon_per_user_limit_reached', ['code' => $couponCode]);
            }
        }

        if ($coupon->min_order_amount !== null && $itemsSubtotal < $coupon->min_order_amount) {
            throw new CartPricingException('coupon_min_order_not_met', [
                'code' => $couponCode,
                'amount' => $coupon->min_order_amount,
            ]);
        }

        // Optional restriction ("فئات أو منتجات محددة اختياريًا"): a coupon
        // with no rows in coupon_categories/coupon_products is unrestricted
        // (discount applies over the whole cart, the original behavior). A
        // restricted coupon only discounts the *eligible* items' subtotal —
        // not the whole cart — so "10% off pizzas" never quietly discounts
        // the drinks in the same order too.
        $eligibleSubtotal = $this->eligibleSubtotalForCoupon($coupon, $pricedItems);

        if ($eligibleSubtotal === 0) {
            throw new CartPricingException('coupon_not_applicable', ['code' => $couponCode]);
        }

        $discount = $coupon->type === CouponType::Percentage
            ? (int) round($eligibleSubtotal * $coupon->value / 100)
            : $coupon->value;

        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, $coupon->max_discount_amount);
        }

        $discount = min($discount, $eligibleSubtotal);

        return [$discount, $coupon->id, $coupon->code];
    }

    /**
     * @param  list<CartPricedItemData>  $pricedItems
     */
    private function eligibleSubtotalForCoupon(Coupon $coupon, array $pricedItems): int
    {
        if (! $coupon->isRestricted()) {
            return array_sum(array_map(fn (CartPricedItemData $item): int => $item->lineTotalAmount, $pricedItems));
        }

        $allowedProductIds = $coupon->products()->pluck('products.id')->all();
        $allowedCategoryIds = $coupon->categories()->pluck('categories.id')->all();

        return array_sum(array_map(
            fn (CartPricedItemData $item): int => (in_array($item->productId, $allowedProductIds, true)
                || in_array($item->categoryId, $allowedCategoryIds, true))
                ? $item->lineTotalAmount
                : 0,
            $pricedItems,
        ));
    }
}
