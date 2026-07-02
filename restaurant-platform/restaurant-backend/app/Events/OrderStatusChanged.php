<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by App\Services\OrderStatusTransitionService only after its
 * database transaction has committed successfully — never from inside the
 * transaction — so a listener can never observe a transition that later
 * gets rolled back.
 */
final class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
        public readonly ?User $actor,
    ) {}
}
