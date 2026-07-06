<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\PaymentStatus;
use App\Events\PaymentStatusChanged;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentStatusUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PaymentStatusUpdateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PaymentStatusUpdateService
    {
        return app(PaymentStatusUpdateService::class);
    }

    public function test_it_updates_the_payment_and_the_orders_mirrored_status(): void
    {
        $order = Order::factory()->create(['payment_status' => PaymentStatus::Pending]);
        $payment = Payment::factory()->for($order)->create(['status' => PaymentStatus::Pending]);

        $this->service()->update($payment, PaymentStatus::Paid);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_it_stamps_paid_at_only_when_moving_to_paid(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->for($order)->create(['status' => PaymentStatus::Pending, 'paid_at' => null]);

        $this->service()->update($payment, PaymentStatus::Paid);

        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_it_does_not_stamp_paid_at_when_moving_to_failed(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->for($order)->create(['status' => PaymentStatus::Pending, 'paid_at' => null]);

        $this->service()->update($payment, PaymentStatus::Failed);

        $this->assertNull($payment->fresh()->paid_at);
    }

    public function test_it_dispatches_payment_status_changed_only_after_commit(): void
    {
        Event::fake([PaymentStatusChanged::class]);

        $order = Order::factory()->create();
        $payment = Payment::factory()->for($order)->create(['status' => PaymentStatus::Pending]);

        $this->service()->update($payment, PaymentStatus::Paid);

        Event::assertDispatched(PaymentStatusChanged::class, function (PaymentStatusChanged $event) use ($order): bool {
            return $event->order->is($order)
                && $event->from === PaymentStatus::Pending
                && $event->to === PaymentStatus::Paid;
        });
    }

    public function test_an_optional_note_is_recorded(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->for($order)->create();

        $this->service()->update($payment, PaymentStatus::Refunded, note: 'Refunded due to a missing item.');

        $this->assertSame('Refunded due to a missing item.', $payment->fresh()->notes);
    }
}
