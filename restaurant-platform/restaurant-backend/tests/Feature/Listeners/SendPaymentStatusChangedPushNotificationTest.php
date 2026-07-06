<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Contracts\PushNotifier;
use App\Enums\PaymentStatus;
use App\Events\PaymentStatusChanged;
use App\Listeners\SendPaymentStatusChangedPushNotification;
use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Push\FakePushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SendPaymentStatusChangedPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function fake(): FakePushNotifier
    {
        /** @var FakePushNotifier $notifier */
        $notifier = app(PushNotifier::class);

        return $notifier;
    }

    private function orderWithDeviceToken(): Order
    {
        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create(['is_active' => true]);

        return Order::factory()->for($user, 'user')->create();
    }

    /**
     * @return array<string, array{PaymentStatus}>
     */
    public static function notifiableStatuses(): array
    {
        return [
            'paid' => [PaymentStatus::Paid],
            'failed' => [PaymentStatus::Failed],
            'refunded' => [PaymentStatus::Refunded],
            'partially_refunded' => [PaymentStatus::PartiallyRefunded],
        ];
    }

    #[DataProvider('notifiableStatuses')]
    public function test_it_sends_a_push_for_each_real_status_change(PaymentStatus $to): void
    {
        $order = $this->orderWithDeviceToken();
        $payment = Payment::factory()->for($order)->create();

        (new SendPaymentStatusChangedPushNotification)->handle(
            new PaymentStatusChanged($order, $payment, PaymentStatus::Pending, $to, null),
        );

        $this->assertCount(1, $this->fake()->sent);
    }

    public function test_it_never_notifies_for_a_change_back_to_pending(): void
    {
        $order = $this->orderWithDeviceToken();
        $payment = Payment::factory()->for($order)->create();

        (new SendPaymentStatusChangedPushNotification)->handle(
            new PaymentStatusChanged($order, $payment, PaymentStatus::Failed, PaymentStatus::Pending, null),
        );

        $this->assertSame([], $this->fake()->sent);
    }

    public function test_the_push_payload_never_includes_the_amount_or_transaction_reference(): void
    {
        $order = $this->orderWithDeviceToken();
        $payment = Payment::factory()->for($order)->create(['transaction_reference' => 'secret-gateway-ref-123']);

        (new SendPaymentStatusChangedPushNotification)->handle(
            new PaymentStatusChanged($order, $payment, PaymentStatus::Pending, PaymentStatus::Paid, null),
        );

        $data = $this->fake()->sent[0]['data'];
        $this->assertArrayNotHasKey('amount', $data);
        $this->assertArrayNotHasKey('transaction_reference', $data);
        $this->assertStringNotContainsString('secret-gateway-ref-123', $this->fake()->sent[0]['body']);
    }
}
