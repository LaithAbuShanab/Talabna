<?php

declare(strict_types=1);

namespace App\Http\Resources\Menu;

use App\Models\DeliveryZone;
use App\Services\MenuCacheService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeliveryZone
 */
final class DeliveryZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currencyCode = app(MenuCacheService::class)->restaurantSettings()->currency_code;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'delivery_fee_amount' => Money::format($this->delivery_fee_amount, $currencyCode),
            'min_order_amount' => $this->min_order_amount !== null
                ? Money::format($this->min_order_amount, $currencyCode)
                : null,
            'estimated_minutes' => $this->estimated_minutes,
        ];
    }
}
