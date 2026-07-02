<?php

declare(strict_types=1);

namespace App\Notifications\Push;

use App\Contracts\PushNotifier;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Default PushNotifier binding (see AppServiceProvider): no real push
 * provider is wired up yet, so this just logs what would have been sent.
 * Replace the binding with a real FCM/APNs/NativePHP-push implementation
 * of PushNotifier when one exists — nothing else needs to change.
 */
final class LogPushNotifier implements PushNotifier
{
    public function send(User $user, string $title, string $body, array $data = []): void
    {
        Log::info('Push notification (no provider configured)', [
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
