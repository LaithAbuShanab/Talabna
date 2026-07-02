<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItemOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItemOption
 */
final class OrderItemOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'option_value_id' => $this->option_value_id,
            'option_group_name' => $this->option_group_name,
            'option_value_name' => $this->option_value_name,
            'price_delta_amount' => $this->price_delta_amount,
        ];
    }
}
