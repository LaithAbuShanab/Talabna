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
 * The full shape used by GET /api/v1/products/{product} — adds
 * description and the nested option groups/values (with add-on prices)
 * that ProductListResource deliberately omits.
 *
 * @mixin Product
 */
final class ProductDetailResource extends JsonResource
{
    use FormatsTranslatable;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currencyCode = app(MenuCacheService::class)->restaurantSettings()->currency_code;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'category' => new CategoryResource($this->category),
            'name' => $this->translatable($this->name, $this->name_ar),
            'description' => $this->translatable($this->description, $this->description_ar),
            'price' => Money::format($this->price_amount, $currencyCode),
            'images' => ProductImageResource::collection($this->images),
            'option_groups' => OptionGroupResource::collection($this->optionGroups),
        ];
    }
}
