<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OptionValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['option_group_id', 'name', 'price_delta_amount', 'is_default', 'sort_order', 'is_active'])]
class OptionValue extends Model
{
    /** @use HasFactory<OptionValueFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price_delta_amount' => 'integer',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class);
    }

    public function orderItemOptions(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }
}
