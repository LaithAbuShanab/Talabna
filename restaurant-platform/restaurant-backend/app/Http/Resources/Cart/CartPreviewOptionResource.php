<?php

declare(strict_types=1);

namespace App\Http\Resources\Cart;

use App\DataTransferObjects\Cart\CartPricedOptionData;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartPricedOptionData
 */
final class CartPreviewOptionResource extends JsonResource
{
    public function __construct(CartPricedOptionData $resource, private readonly string $currencyCode)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'option_value_id' => $this->optionValueId,
            'option_group_name' => $this->optionGroupName,
            'option_value_name' => $this->optionValueName,
            'price_delta' => Money::format($this->priceDeltaAmount, $this->currencyCode),
        ];
    }
}
