<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Contracts\PushNotifier;
use App\Jobs\SendCustomerPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\NotificationDispatchLog;
use App\Models\User;
use App\Notifications\Push\FakePushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers App\Jobs\SendCustomerPushNotificationJob directly (no queue
 * connection involved — phpunit.xml forces QUEUE_CONNECTION=sync, so
 * `dispatchSync()` runs `handle()` immediately, exactly like a real queue
 * worker would). App\Notifications\Push\FakePushNotifier is the
 * "في بيئة الاختبار استخدم fake provider" double bound by
 * AppServiceProvider whenever `app()->environment('testing')`.
 */
class SendCustomerPushNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    private function fake(): FakePushNotifier
    {
        /** @var FakePushNotifier $notifier */
        $notifier = app(PushNotifier::class);

        return $notifier;
    }

    public function test_it_is_bound_to_the_fake_provider_in_tests(): void
    {
        $this->assertInstanceOf(FakePushNotifier::class, app(PushNotifier::class));
    }

    public function test_it_sends_to_every_active_device_token(): void
    {
        $user = User::factory()->create();
        $tokenA = DeviceToken::factory()->for($user)->create(['is_active' => true]);
        $tokenB = DeviceToken::factory()->for($user)->create(['is_active' => true]);
        DeviceToken::factory()->for($user)->create(['is_active' => false]); // inactive: skipped

        SendCustomerPushNotificationJob::dispatchSync(
            userId: $user->id,
            title: 'Order accepted',
            body: 'Your order #123 has been accepted.',
            data: ['order_id' => 123],
            idempotencyKey: 'push:test:1',
        );

        $sentTokenIds = array_column($this->fake()->sent, 'token_id');
        $this->assertEqualsCanonicalizing([$tokenA->id, $tokenB->id], $sentTokenIds);
    }

    public function test_it_updates_last_used_at_on_successful_delivery(): void
    {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->for($user)->create(['is_active' => true, 'last_used_at' => null]);

        SendCustomerPushNotificationJob::dispatchSync($user->id, 'T', 'B', [], 'push:test:2');

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    public function test_it_deactivates_a_token_the_provider_reports_as_invalid(): void
    {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->for($user)->create(['is_active' => true]);
        $this->fake()->invalidTokenIds = [$token->id];

        SendCustomerPushNotificationJob::dispatchSync($user->id, 'T', 'B', [], 'push:test:3');

        $this->assertFalse($token->fresh()->is_active);
    }

    public function test_it_never_calls_the_provider_for_an_already_inactive_token(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create(['is_active' => false]);

        SendCustomerPushNotificationJob::dispatchSync($user->id, 'T', 'B', [], 'push:test:4');

        $this->assertSame([], $this->fake()->sent);
    }

    public function test_it_is_a_no_op_when_the_user_no_longer_exists(): void
    {
        SendCustomerPushNotificationJob::dispatchSync(999999, 'T', 'B', [], 'push:test:5');

        $this->assertSame([], $this->fake()->sent);
    }

    // --- Idempotency ---------------------------------------------------

    public function test_a_second_dispatch_with_the_same_idempotency_key_sends_nothing(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create(['is_active' => true]);

        SendCustomerPushNotificationJob::dispatchSync($user->id, 'T', 'B', [], 'push:test:dup');
        SendCustomerPushNotificationJob::dispatchSync($user->id, 'T', 'B', [], 'push:test:dup');

        $this->assertCount(1, $this->fake()->sent);
    }

    public function test_different_idempotency_keys_both_send(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create(['is_active' => true]);

        SendCustomerPushNotificationJob::dispatchSync($user->id, 'T', 'B', [], 'push:test:a');
        SendCustomerPushNotificationJob::dispatchSync($user->id, 'T', 'B', [], 'push:test:b');

        $this->assertCount(2, $this->fake()->sent);
    }

    public function test_a_transient_failure_releases_the_claim_so_a_retry_can_send(): void
    {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->for($user)->create(['is_active' => true]);
        $this->fake()->failingTokenIds = [$token->id];

        try {
            SendCustomerPushNotificationJob::dispatchSync($user->id, 'T', 'B', [], 'push:test:retry');
            $this->fail('Expected the simulated provider failure to propagate.');
        } catch (RuntimeException) {
            // expected: the job lets the exception bubble up so the queue
            // worker's own retry/backoff takes over.
        }

        $this->assertFalse(
            NotificationDispatchLog::query()->where('idempotency_key', 'push:test:retry')->exists(),
            'A failed attempt must release its idempotency claim.',
        );

        // The retry: this time the provider succeeds.
        $this->fake()->failingTokenIds = [];
        SendCustomerPushNotificationJob::dispatchSync($user->id, 'T', 'B', [], 'push:test:retry');

        $this->assertCount(1, $this->fake()->sent);
    }

    public function test_a_successful_send_is_never_released_so_it_cannot_be_repeated(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create(['is_active' => true]);

        SendCustomerPushNotificationJob::dispatchSync($user->id, 'T', 'B', [], 'push:test:permanent');

        $this->assertTrue(
            NotificationDispatchLog::query()->where('idempotency_key', 'push:test:permanent')->exists(),
        );
    }
}
