<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\PushDeliveryResult;
use App\Models\DeviceToken;

/**
 * The seam between "something happened, notify a user" and whichever push
 * provider actually sends it (FCM, APNs, NativePHP push, ...). Nothing in
 * this codebase — no Event, Listener, or Job — depends on a concrete
 * provider directly, only on this interface, so swapping providers later is
 * a one-line binding change in App\Providers\AppServiceProvider, never a
 * change to a call site.
 *
 * Deliberately per-token, not per-user: a user can have several device
 * tokens (multiple phones, reinstalls), and the caller
 * (App\Jobs\SendCustomerPushNotificationJob) needs to know the outcome of
 * *each* token's delivery attempt individually — specifically whether the
 * provider says a token is no longer valid, so it can be deactivated
 * (`App\Models\DeviceToken.is_active`) without touching the user's other
 * tokens. A genuinely transient failure (provider unreachable, rate
 * limited) should be a thrown exception, not a return value — that's what
 * lets the Job's own retry/backoff take over.
 */
interface PushNotifier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function sendToToken(DeviceToken $token, string $title, string $body, array $data = []): PushDeliveryResult;
}
