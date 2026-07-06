<?php

declare(strict_types=1);

namespace App\Http\Resources\Cart;

use App\DataTransferObjects\Cart\CartPricingResultData;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps App\DataTransferObjects\Cart\CartPricingResultData — the same
 * computation App\Actions\CreateOrderAction uses for real checkout, so a
 * preview is always exactly what an order would cost. Coupon validation
 * happens as part of this same result: an invalid/expired/exhausted coupon
 * throws CartPricingException before a result ever exists (see
 * bootstrap/app.php for how that renders as a 422).
 *
 * @mixin CartPricingResultData
 */
final class CartPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->currencyCode;

        return [
            'items' => collect($this->items)
                ->map(fn ($item) => new CartPreviewItemResource($item, $currency))
                ->values(),
            'currency' => $currency,
            'items_subtotal' => Money::format($this->itemsSubtotalAmount, $currency),
            'options_total' => Money::format($this->optionsTotalAmount, $currency),
            'coupon' => $this->appliedCouponCode !== null ? [
                'id' => $this->appliedCouponId,
                'code' => $this->appliedCouponCode,
            ] : null,
            'discount_amount' => Money::format($this->discountAmount, $currency),
            'delivery' => [
                'type' => $this->deliveryType->value,
                'zone_id' => $this->deliveryZoneId,
                'fee_amount' => Money::format($this->deliveryFeeAmount, $currency),
            ],
            'tax' => [
                'applied' => $this->isTaxApplied,
                'inclusive' => $this->isTaxInclusive,
                'amount' => Money::format($this->taxAmount, $currency),
            ],
            'grand_total' => Money::format($this->grandTotalAmount, $currency),
        ];
    }
}
