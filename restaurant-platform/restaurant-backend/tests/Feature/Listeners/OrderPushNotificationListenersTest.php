<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Contracts\PushNotifier;
use App\Events\OrderAccepted;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderDelivered;
use App\Events\OrderOutForDelivery;
use App\Events\OrderPreparing;
use App\Events\OrderReady;
use App\Events\OrderRejected;
use App\Listeners\SendOrderAcceptedPushNotification;
use App\Listeners\SendOrderCancelledPushNotification;
use App\Listeners\SendOrderCreatedPushNotification;
use App\Listeners\SendOrderDeliveredPushNotification;
use App\Listeners\SendOrderOutForDeliveryPushNotification;
use App\Listeners\SendOrderPreparingPushNotification;
use App\Listeners\SendOrderReadyPushNotification;
use App\Listeners\SendOrderRejectedPushNotification;
use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Push\FakePushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One test per Send*PushNotification listener, covering every order-status
 * event required by the task: created, accepted, rejected, preparing,
 * ready, out_for_delivery, delivered, cancelled. Each listener is called
 * directly (not via Event::dispatch) — the wiring from
 * App\Services\OrderStatusTransitionService is covered separately in
 * OrderStatusTransitionServiceTest. QUEUE_CONNECTION=sync in tests means
 * App\Jobs\SendCustomerPushNotificationJob runs inline, so asserting on
 * App\Notifications\Push\FakePushNotifier's recorded sends is a true
 * end-to-end check of "the customer's device actually gets a push".
 */
class OrderPushNotificationListenersTest extends TestCase
{
    use RefreshDatabase;

    private function fake(): FakePushNotifier
    {
        /** @var FakePushNotifier $notifier */
        $notifier = app(PushNotifier::class);

        return $notifier;
    }

    private function orderWithDeviceToken(array $attributes = []): Order
    {
        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create(['is_active' => true]);

        return Order::factory()->for($user, 'user')->create($attributes);
    }

    public function test_order_created_sends_a_push(): void
    {
        $order = $this->orderWithDeviceToken();

        (new SendOrderCreatedPushNotification)->handle(new OrderCreated($order));

        $this->assertCount(1, $this->fake()->sent);
        $this->assertStringContainsString($order->order_number, $this->fake()->sent[0]['body']);
    }

    public function test_order_accepted_sends_a_push(): void
    {
        $order = $this->orderWithDeviceToken();

        (new SendOrderAcceptedPushNotification)->handle(new OrderAccepted($order, null));

        $this->assertCount(1, $this->fake()->sent);
    }

    public function test_order_rejected_sends_a_push_including_the_reason(): void
    {
        $order = $this->orderWithDeviceToken(['rejection_reason' => 'Out of stock']);

        (new SendOrderRejectedPushNotification)->handle(new OrderRejected($order, null));

        $this->assertCount(1, $this->fake()->sent);
        $this->assertStringContainsString('Out of stock', $this->fake()->sent[0]['body']);
    }

    public function test_order_preparing_sends_a_push(): void
    {
        $order = $this->orderWithDeviceToken();

        (new SendOrderPreparingPushNotification)->handle(new OrderPreparing($order, null));

        $this->assertCount(1, $this->fake()->sent);
    }

    public function test_order_ready_sends_a_push(): void
    {
        $order = $this->orderWithDeviceToken();

        (new SendOrderReadyPushNotification)->handle(new OrderReady($order, null));

        $this->assertCount(1, $this->fake()->sent);
    }

    public function test_order_out_for_delivery_sends_a_push(): void
    {
        $order = $this->orderWithDeviceToken();

        (new SendOrderOutForDeliveryPushNotification)->handle(new OrderOutForDelivery($order, null));

        $this->assertCount(1, $this->fake()->sent);
    }

    public function test_order_delivered_sends_a_push(): void
    {
        $order = $this->orderWithDeviceToken();

        (new SendOrderDeliveredPushNotification)->handle(new OrderDelivered($order, null));

        $this->assertCount(1, $this->fake()->sent);
    }

    public function test_order_cancelled_sends_a_push_including_the_reason(): void
    {
        $order = $this->orderWithDeviceToken(['cancellation_reason' => 'Customer changed their mind']);

        (new SendOrderCancelledPushNotification)->handle(new OrderCancelled($order, null));

        $this->assertCount(1, $this->fake()->sent);
        $this->assertStringContainsString('Customer changed their mind', $this->fake()->sent[0]['body']);
    }

    public function test_each_event_type_uses_a_distinct_idempotency_key_for_the_same_order(): void
    {
        $order = $this->orderWithDeviceToken();

        (new SendOrderCreatedPushNotification)->handle(new OrderCreated($order));
        (new SendOrderAcceptedPushNotification)->handle(new OrderAccepted($order, null));

        // Both must have been delivered — if the keys collided, the second
        // would have been silently skipped as a "duplicate".
        $this->assertCount(2, $this->fake()->sent);
    }

    public function test_no_push_is_sent_when_the_customer_has_no_device_tokens(): void
    {
        $order = Order::factory()->create();

        (new SendOrderAcceptedPushNotification)->handle(new OrderAccepted($order, null));

        $this->assertSame([], $this->fake()->sent);
    }
}
