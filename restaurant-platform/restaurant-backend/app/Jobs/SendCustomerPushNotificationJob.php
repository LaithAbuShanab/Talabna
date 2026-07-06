<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\PushNotifier;
use App\Enums\PushDeliveryResult;
use App\Models\NotificationDispatchLog;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * The one and only queued unit of work that ever calls out to a push
 * provider (via App\Contracts\PushNotifier) — every Send*PushNotification
 * listener builds its (already-translated) title/body and dispatches this
 * Job rather than sending directly, so "queue جميع الإشعارات الخارجية"
 * holds for every customer-facing push in the app.
 *
 * **Idempotency** ("منع إرسال إشعار مكرر لنفس event عند retry"):
 * `$idempotencyKey` is claimed via App\Models\NotificationDispatchLog
 * *before* anything is sent. If the claim fails (already claimed by an
 * earlier attempt/dispatch of the same event), this job is a silent no-op —
 * the event has already been handled. If claimed but sending then throws,
 * the claim is released before rethrowing, so a queue-level retry can claim
 * it again and actually try. The one accepted gap: if several device tokens
 * are sent to and a *later* token throws, the *earlier* tokens in the same
 * attempt may receive the push again on retry — an acceptable trade-off
 * given most customers have a single active device token, and the
 * alternative (per-token idempotency) adds real complexity for a
 * vanishingly rare case.
 *
 * **Retry/backoff** ("retry وbackoff واضحان"): 5 attempts, waiting 10s, 30s,
 * 60s, 5m, then 15m between them — long enough that a brief provider outage
 * clears before giving up, short enough that a real delivery isn't stuck
 * for hours.
 *
 * **No sensitive data** ("عدم تضمين بيانات حساسة داخل push payload"):
 * `$data` is whatever the calling Listener builds — by convention (see
 * every Send*PushNotification listener) that's only IDs and status values
 * already visible to the customer through the app's own API (order_id,
 * order_number, status), never payment references, addresses, or anything
 * from `RestaurantSetting`'s encrypted fields.
 */
final class SendCustomerPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data,
        public readonly string $idempotencyKey,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300, 900];
    }

    public function handle(PushNotifier $notifier): void
    {
        if (! NotificationDispatchLog::claim($this->idempotencyKey)) {
            return;
        }

        $user = User::find($this->userId);

        if (! $user instanceof User) {
            return;
        }

        $tokens = $user->deviceTokens()->where('is_active', true)->get();

        try {
            foreach ($tokens as $token) {
                $result = $notifier->sendToToken($token, $this->title, $this->body, $this->data);

                match ($result) {
                    PushDeliveryResult::Sent => $token->update(['last_used_at' => now()]),
                    PushDeliveryResult::InvalidToken => $token->update(['is_active' => false]),
                };
            }
        } catch (Throwable $e) {
            NotificationDispatchLog::release($this->idempotencyKey);

            throw $e;
        }
    }
}
