<?php

declare(strict_types=1);

namespace App\Http\Resources\Menu;

use App\Http\Resources\Concerns\FormatsTranslatable;
use App\Models\Product;
use App\Services\MenuCacheService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The lean shape used by GET /api/v1/products (list/search). Deliberately
 * excludes description and option groups/values — those are only in
 * ProductDetailResource, so listing many products at once never pulls in
 * a large nested options payload for each one. See docs/API_MENU.md.
 *
 * @mixin Product
 */
final class ProductListResource extends JsonResource
{
    use FormatsTranslatable;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primaryImage = $this->images->firstWhere('is_primary', true) ?? $this->images->first();
        $currencyCode = app(MenuCacheService::class)->restaurantSettings()->currency_code;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'category_id' => $this->category_id,
            'name' => $this->translatable($this->name, $this->name_ar),
            'price' => Money::format($this->price_amount, $currencyCode),
            'image_url' => $primaryImage !== null ? asset($primaryImage->path) : null,
        ];
    }
}
