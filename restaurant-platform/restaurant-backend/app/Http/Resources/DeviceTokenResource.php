<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeviceToken
 */
final class DeviceTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'device_name' => $this->device_name,
            'is_active' => $this->is_active,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
        ];
    }
}
