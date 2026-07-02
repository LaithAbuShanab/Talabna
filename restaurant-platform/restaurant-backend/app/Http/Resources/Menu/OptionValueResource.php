<?php

declare(strict_types=1);

namespace App\Http\Resources\Menu;

use App\Http\Resources\Concerns\FormatsTranslatable;
use App\Models\OptionValue;
use App\Services\MenuCacheService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OptionValue
 */
final class OptionValueResource extends JsonResource
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
            'name' => $this->translatable($this->name, $this->name_ar),
            'price_delta' => Money::format($this->price_delta_amount, $currencyCode),
            'is_default' => $this->is_default,
        ];
    }
}
