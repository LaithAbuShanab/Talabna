<?php

declare(strict_types=1);

namespace App\Http\Resources\Menu;

use App\Models\RestaurantSetting;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RestaurantSetting
 */
final class RestaurantSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->restaurant_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'currency' => $this->currency_code,
            'min_order_amount' => Money::format($this->min_order_amount, $this->currency_code),
            'default_delivery_fee_amount' => Money::format($this->default_delivery_fee_amount, $this->currency_code),
            'default_preparation_minutes' => $this->default_preparation_minutes,
            'is_accepting_orders' => $this->is_accepting_orders,
            'allows_scheduled_orders' => $this->allows_scheduled_orders,
            'tax' => [
                'enabled' => $this->is_tax_enabled,
                'rate_percent' => round($this->tax_rate_bps / 100, 2),
            ],
        ];
    }
}
