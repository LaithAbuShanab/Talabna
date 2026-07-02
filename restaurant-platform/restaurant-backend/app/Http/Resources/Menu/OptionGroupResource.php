<?php

declare(strict_types=1);

namespace App\Http\Resources\Menu;

use App\Http\Resources\Concerns\FormatsTranslatable;
use App\Models\OptionGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product's option group, including the per-product pivot fields
 * (is_required/min_select/max_select) — see
 * App\Services\CartPricingService::assertOptionGroupSelections() for the
 * exact same effective-min/max fallback rule applied at pricing time.
 *
 * @mixin OptionGroup
 */
final class OptionGroupResource extends JsonResource
{
    use FormatsTranslatable;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pivot = $this->pivot;
        $isRequired = (bool) $pivot->is_required;
        $effectiveMin = $pivot->min_select ?? ($isRequired ? 1 : 0);
        $effectiveMax = $pivot->max_select ?? ($this->selection_type->value === 'single' ? 1 : null);

        return [
            'id' => $this->id,
            'name' => $this->translatable($this->name, $this->name_ar),
            'selection_type' => $this->selection_type->value,
            'is_required' => $isRequired,
            'min_select' => $effectiveMin,
            'max_select' => $effectiveMax,
            'values' => OptionValueResource::collection($this->values),
        ];
    }
}
