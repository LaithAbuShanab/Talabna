<?php

declare(strict_types=1);

namespace App\Notifications\Push;

use App\Contracts\PushNotifier;
use App\Enums\PushDeliveryResult;
use App\Models\DeviceToken;
use RuntimeException;

/**
 * The PushNotifier bound in the `testing` environment (see
 * AppServiceProvider) — "في بيئة الاختبار استخدم fake provider": no test
 * ever reaches a real push provider over the network, on purpose. Records
 * every call in memory so tests can assert on exactly what would have been
 * sent, and lets a test pre-configure specific device-token IDs to come
 * back as an invalid token or a thrown (transient) failure, to exercise
 * App\Jobs\SendCustomerPushNotificationJob's handling of both.
 */
final class FakePushNotifier implements PushNotifier
{
    /**
     * @var list<array{token_id: int, platform: string, title: string, body: string, data: array<string, mixed>}>
     */
    public array $sent = [];

    /** @var list<int> */
    public array $invalidTokenIds = [];

    /** @var list<int> */
    public array $failingTokenIds = [];

    public function sendToToken(DeviceToken $token, string $title, string $body, array $data = []): PushDeliveryResult
    {
        if (in_array($token->id, $this->failingTokenIds, true)) {
            throw new RuntimeException("Simulated transient push provider failure for device token {$token->id}.");
        }

        $this->sent[] = [
            'token_id' => $token->id,
            'platform' => $token->platform->value,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ];

        if (in_array($token->id, $this->invalidTokenIds, true)) {
            return PushDeliveryResult::InvalidToken;
        }

        return PushDeliveryResult::Sent;
    }
}
