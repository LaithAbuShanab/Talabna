<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\RestaurantSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full order representation returned after checkout. delivery_address
 * is built from the order's own snapshot columns
 * (delivery_address_line/city/latitude/longitude), never from the live
 * CustomerAddress — see docs/DATABASE_SCHEMA.md "Snapshotting".
 *
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'delivery_type' => $this->delivery_type->value,
            'payment_method' => $this->payment_method->value,
            'payment_status' => $this->payment_status->value,
            'currency_code' => RestaurantSetting::current()->currency_code,
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'delivery_fee_amount' => $this->delivery_fee_amount,
            'total_amount' => $this->total_amount,
            'applied_coupon_code' => $this->coupon?->code,
            'delivery_address' => $this->delivery_type->value === 'delivery' ? [
                'address_line' => $this->delivery_address_line,
                'city' => $this->delivery_city,
                'latitude' => $this->delivery_latitude,
                'longitude' => $this->delivery_longitude,
            ] : null,
            'customer_notes' => $this->customer_notes,
            'rejection_reason' => $this->rejection_reason,
            'cancellation_reason' => $this->cancellation_reason,
            'expected_delivery_at' => $this->expected_delivery_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'status_histories' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
