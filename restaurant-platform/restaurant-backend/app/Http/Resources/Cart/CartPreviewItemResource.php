<?php

declare(strict_types=1);

namespace App\Http\Resources\Cart;

use App\DataTransferObjects\Cart\CartPricedItemData;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartPricedItemData
 */
final class CartPreviewItemResource extends JsonResource
{
    public function __construct(CartPricedItemData $resource, private readonly string $currencyCode)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'quantity' => $this->quantity,
            'unit_base_price' => Money::format($this->unitBasePriceAmount, $this->currencyCode),
            'options' => collect($this->options)
                ->map(fn ($option) => new CartPreviewOptionResource($option, $this->currencyCode))
                ->values(),
            'unit_options_total' => Money::format($this->unitOptionsTotalAmount, $this->currencyCode),
            'unit_total' => Money::format($this->unitTotalAmount, $this->currencyCode),
            'line_total' => Money::format($this->lineTotalAmount, $this->currencyCode),
        ];
    }
}
