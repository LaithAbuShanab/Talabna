<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The idempotency ledger behind "منع إرسال إشعار مكرر لنفس event عند retry"
 * — see App\Jobs\SendCustomerPushNotificationJob. `claim()` atomically
 * reserves a key via the unique index on `idempotency_key` (an
 * `insertOrIgnore`, so it's race-safe across two workers processing the
 * same job concurrently, not just sequential retries); `release()` undoes
 * that reservation when a delivery attempt fails with a genuine
 * (retryable) error, so the *next* retry can claim the same key again.
 * A key that was claimed and never released means "this event was already
 * successfully handed to the push provider" — permanently, on purpose.
 */
#[Fillable(['idempotency_key'])]
class NotificationDispatchLog extends Model
{
    public static function claim(string $key): bool
    {
        return DB::table('notification_dispatch_logs')->insertOrIgnore([
            'idempotency_key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]) > 0;
    }

    public static function release(string $key): void
    {
        DB::table('notification_dispatch_logs')->where('idempotency_key', $key)->delete();
    }
}
