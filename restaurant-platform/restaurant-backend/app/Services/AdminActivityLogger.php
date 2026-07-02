<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes to the simple, append-only admin audit trail
 * (App\Models\AdminActivityLog) — see docs/ADMIN_PANEL.md. Deliberately
 * called from a small, curated set of call sites for genuinely sensitive
 * actions (role/activation changes, order status changes made by staff,
 * settings updates, admin account create/delete) rather than wired as a
 * generic auto-observer on every model — a deliberate choice to keep the
 * trail meaningful and easy to read instead of drowning in routine writes.
 */
final class AdminActivityLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(?User $actor, string $action, ?Model $subject = null, ?string $description = null, array $metadata = []): AdminActivityLog
    {
        return AdminActivityLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
