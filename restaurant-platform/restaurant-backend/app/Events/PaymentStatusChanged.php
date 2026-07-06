<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by App\Services\PaymentStatusUpdateService only after its
 * database transaction has committed successfully — never from inside it,
 * same rule as every other domain event in this app (see
 * App\Events\OrderCreated, App\Events\OrderStatusChanged).
 */
final class PaymentStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly Payment $payment,
        public readonly PaymentStatus $from,
        public readonly PaymentStatus $to,
        public readonly ?User $actor,
    ) {}
}
