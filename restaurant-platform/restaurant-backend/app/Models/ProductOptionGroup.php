<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The product<->option_group link, e.g. "Pizza" -> "Size". Modeled as a
 * real Eloquent (pivot) class rather than a plain array pivot because it
 * carries its own attribute (is_required is a per-product decision — the
 * same "Size" group might be required for pizzas but optional for drinks).
 */
#[Fillable(['product_id', 'option_group_id', 'is_required', 'sort_order'])]
class ProductOptionGroup extends Pivot
{
    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class);
    }
}
