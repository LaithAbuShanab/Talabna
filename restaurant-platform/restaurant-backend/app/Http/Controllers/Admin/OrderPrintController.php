<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\RestaurantSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * A bare, thermal-printer-friendly HTML receipt for one order — reached
 * from App\Filament\Resources\Orders\Pages\ViewOrder's "Print" action (opens
 * in a new tab; the browser's own print dialog does the rest). Deliberately
 * a plain Blade view with no Filament/app layout at all: a receipt printed
 * on 80mm thermal paper must not carry a sidebar/topbar along with it. No
 * external printing service is integrated — see docs/ADMIN_ORDERS.md.
 *
 * Authorization reuses App\Policies\OrderPolicy::view() — the same ability
 * OrderResource's view page and the customer-facing API already check —
 * rather than inventing a separate rule for "who can print a receipt."
 */
final class OrderPrintController
{
    public function __invoke(Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['user', 'coupon', 'items.options', 'payments']);

        return view('admin.orders.print', [
            'order' => $order,
            'currencyCode' => RestaurantSetting::current()->currency_code,
        ]);
    }
}
