<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AdminActivityLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * A simple, append-only audit trail row — see App\Services\AdminActivityLogger
 * and docs/ADMIN_PANEL.md. No `updated_at` column; rows are never edited
 * or deleted once written (enforced below, not just by convention — same
 * pattern as App\Models\OrderStatusHistory).
 */
#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'description', 'metadata'])]
class AdminActivityLog extends Model
{
    /** @use HasFactory<AdminActivityLogFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('AdminActivityLog records are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('AdminActivityLog records are append-only and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
