<?php

declare(strict_types=1);

namespace App\Http\Resources\Menu;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductImage
 */
final class ProductImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => asset($this->path),
            'is_primary' => $this->is_primary,
        ];
    }
}
