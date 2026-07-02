<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit trail of every order status transition: rows are
 * created once and never updated or deleted, enforced below (not just by
 * convention) — see docs/DATABASE_SCHEMA.md. No updated_at column exists
 * on purpose.
 */
#[Fillable(['order_id', 'status', 'note', 'changed_by_user_id'])]
class OrderStatusHistory extends Model
{
    /** @use HasFactory<OrderStatusHistoryFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('OrderStatusHistory records are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('OrderStatusHistory records are append-only and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
