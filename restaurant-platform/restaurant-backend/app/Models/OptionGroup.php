<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptionSelectionType;
use Database\Factories\OptionGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'selection_type', 'sort_order'])]
class OptionGroup extends Model
{
    /** @use HasFactory<OptionGroupFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'selection_type' => OptionSelectionType::class,
            'sort_order' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_option_groups')
            ->using(ProductOptionGroup::class)
            ->withPivot(['is_required', 'min_select', 'max_select', 'sort_order'])
            ->withTimestamps();
    }
}
