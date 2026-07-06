<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DashboardPeriod;
use App\Models\Order;
use App\Models\RestaurantSetting;
use App\Support\Csv;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "إمكانية تصدير تقرير طلبات CSV بطريقة آمنة" — reached from
 * App\Filament\Pages\Dashboard's "Export orders (CSV)" header action,
 * exporting whatever period is currently selected there (see
 * App\Enums\DashboardPeriod). Streamed (`chunk()` + `streamDownload()`),
 * not loaded into memory as one array, so this stays cheap regardless of
 * how many orders fall in the selected range.
 *
 * Authorization reuses App\Policies\OrderPolicy::viewAny() — the same
 * ability that gates the Orders list itself, since this is a read-only
 * export of the same data, not a new capability.
 *
 * **CSV injection prevention** ("منع CSV injection"): every cell goes
 * through App\Support\Csv::sanitizeCell(), which neutralizes any value
 * that *starts* with `=`, `+`, `-`, `@`, a tab, or a carriage return —
 * the classic formula-injection vector spreadsheet apps (not this server)
 * would otherwise execute when the file is opened, most plausibly via a
 * customer's free-text `name`. A UTF-8 BOM is written first so Arabic
 * customer names render correctly in Excel rather than as mojibake.
 */
final class OrderExportController
{
    public function __invoke(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Order::class);

        $period = DashboardPeriod::tryFrom((string) $request->query('period')) ?? DashboardPeriod::Today;
        [$start, $end] = $period->range();
        $currencyCode = RestaurantSetting::current()->currency_code;
        $filename = "orders-{$period->value}-".now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($start, $end, $currencyCode): void {
            $handle = fopen('php://output', 'wb');

            // BOM: Excel needs this to render non-ASCII (Arabic) text correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Order #', 'Customer', 'Email', 'Status', 'Payment status',
                'Payment method', 'Delivery type', 'Subtotal', 'Discount',
                'Delivery fee', 'Total', 'Placed at',
            ]);

            Order::query()
                ->with('user')
                ->whereBetween('created_at', [$start, $end])
                ->orderBy('created_at')
                ->chunk(200, function ($orders) use ($handle, $currencyCode): void {
                    foreach ($orders as $order) {
                        fputcsv($handle, array_map(
                            Csv::sanitizeCell(...),
                            [
                                $order->order_number,
                                $order->user?->name,
                                $order->user?->email,
                                $order->status->getLabel(),
                                $order->payment_status->getLabel(),
                                $order->payment_method->getLabel(),
                                $order->delivery_type->getLabel(),
                                Money::format($order->subtotal_amount, $currencyCode)['formatted'],
                                Money::format($order->discount_amount, $currencyCode)['formatted'],
                                Money::format($order->delivery_fee_amount, $currencyCode)['formatted'],
                                Money::format($order->total_amount, $currencyCode)['formatted'],
                                $order->created_at?->toDateTimeString(),
                            ],
                        ));
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
