<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
final class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_price_amount' => $this->product_price_amount,
            'quantity' => $this->quantity,
            'unit_total_amount' => $this->unit_total_amount,
            'line_total_amount' => $this->line_total_amount,
            'options' => OrderItemOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
