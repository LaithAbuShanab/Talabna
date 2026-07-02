<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderItemOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of a selected option at order time — see docs/DATABASE_SCHEMA.md.
 */
#[Fillable(['order_item_id', 'option_value_id', 'option_group_name', 'option_value_name', 'price_delta_amount'])]
class OrderItemOption extends Model
{
    /** @use HasFactory<OrderItemOptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_delta_amount' => 'integer',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(OptionValue::class);
    }
}
