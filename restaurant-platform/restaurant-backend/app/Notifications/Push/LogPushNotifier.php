<?php

declare(strict_types=1);

namespace App\Notifications\Push;

use App\Contracts\PushNotifier;
use App\Enums\PushDeliveryResult;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;

/**
 * Default non-testing PushNotifier binding (see AppServiceProvider): no real
 * push provider is wired up yet, so this just logs what would have been
 * sent and reports every token as successfully delivered. Replace the
 * binding with a real FCM/APNs/NativePHP-push implementation of
 * PushNotifier when one exists — nothing else needs to change, including
 * App\Jobs\SendCustomerPushNotificationJob.
 */
final class LogPushNotifier implements PushNotifier
{
    public function sendToToken(DeviceToken $token, string $title, string $body, array $data = []): PushDeliveryResult
    {
        Log::info('Push notification (no provider configured)', [
            'device_token_id' => $token->id,
            'platform' => $token->platform->value,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        return PushDeliveryResult::Sent;
    }
}
