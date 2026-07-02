<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by App\Actions\CreateOrderAction only after its database
 * transaction has committed successfully — never from inside the
 * transaction — so a listener can never observe an order that later gets
 * rolled back.
 */
final class OrderCreated
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
