<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The lean "what's the state of this order right now" snapshot — used by
 * the polling endpoint (`GET /orders/{order}/status`,
 * App\Http\Controllers\Api\V1\OrderController::status()) and deliberately
 * kept separate from the full `OrderResource` (items/payments/coupon/
 * addresses), so a client checking on progress every few seconds never
 * pulls more than this.
 *
 * This is also the seam docs/ORDER_STATUS_POLLING.md's "future real-time"
 * section points at: if/when this app adds WebSockets or Laravel Reverb,
 * a broadcastable order-status event would reuse this exact same Resource
 * for its `broadcastWith()` payload, so the client-side parsing code for
 * "what does an order-status update look like" never has to change, only
 * how it arrives.
 *
 * @mixin Order
 */
final class OrderStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'expected_delivery_at' => $this->expected_delivery_at?->toIso8601String(),
            'can_be_cancelled' => $this->status->isCustomerCancellable(),
            'timeline' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
        ];
    }
}
