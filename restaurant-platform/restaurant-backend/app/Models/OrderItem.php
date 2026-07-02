<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * product_name/product_price_amount are a snapshot taken at order time —
 * see docs/DATABASE_SCHEMA.md. product() may resolve to null if the
 * product was later deleted; that's fine, the snapshot fields remain
 * authoritative.
 */
#[Fillable(['order_id', 'product_id', 'product_name', 'product_price_amount', 'quantity', 'unit_total_amount', 'line_total_amount'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'product_price_amount' => 'integer',
            'quantity' => 'integer',
            'unit_total_amount' => 'integer',
            'line_total_amount' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }
}
