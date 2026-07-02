<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id',
    'name',
    'name_ar',
    'slug',
    'description',
    'description_ar',
    'price_amount',
    'is_active',
    'is_available',
    'sort_order',
    'preparation_minutes',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'sort_order' => 'integer',
            'preparation_minutes' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(OptionGroup::class, 'product_option_groups')
            ->using(ProductOptionGroup::class)
            ->withPivot(['is_required', 'min_select', 'max_select', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * The same product<->option_group link as optionGroups(), exposed as
     * a plain HasMany over the pivot model instead of a BelongsToMany —
     * lets App\Filament\Resources\Products\RelationManagers\OptionGroupsRelationManager
     * manage `is_required`/`min_select`/`max_select` through an ordinary
     * create/edit form (a Select for option_group_id + the pivot fields),
     * rather than needing Filament's attach/detach pivot-form APIs.
     */
    public function productOptionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
