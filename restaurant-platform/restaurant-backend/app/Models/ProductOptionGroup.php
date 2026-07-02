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
 *
 * `$table` is set explicitly rather than left to Eloquent's convention:
 * Illuminate\Database\Eloquent\Relations\Concerns\AsPivot::getTable()
 * overrides the usual "snake_case, pluralized" guess with a *singular*
 * one for any Pivot subclass whose `$table` isn't already set (harmless
 * for Product::optionGroups()'s BelongsToMany below, which always passes
 * the table name explicitly — but Product::productOptionGroups()'s plain
 * HasMany relation has no such explicit table and would otherwise query
 * the nonexistent `product_option_group` table instead of
 * `product_option_groups`).
 */
#[Fillable(['product_id', 'option_group_id', 'is_required', 'min_select', 'max_select', 'sort_order'])]
class ProductOptionGroup extends Pivot
{
    protected $table = 'product_option_groups';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'min_select' => 'integer',
            'max_select' => 'integer',
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
