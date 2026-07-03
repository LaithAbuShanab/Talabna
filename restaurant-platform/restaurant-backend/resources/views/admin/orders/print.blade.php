<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Order #{{ $order->order_number }}</title>
    <style>
        /* Sized for an 80mm thermal receipt printer. No external assets,
           no layout chrome — this page is meant to be opened and printed
           directly, nothing else. */
        @page {
            size: 80mm auto;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            width: 80mm;
            margin: 0 auto;
            padding: 4mm;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 2mm;
            text-align: center;
        }

        .center {
            text-align: center;
        }

        .muted {
            color: #444;
        }

        hr {
            border: none;
            border-top: 1px dashed #000;
            margin: 2mm 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 0.5mm 0;
            vertical-align: top;
        }

        .align-right {
            text-align: right;
            white-space: nowrap;
        }

        .totals td {
            padding: 0.5mm 0;
        }

        .grand-total {
            font-weight: bold;
            font-size: 14px;
        }

        .print-button {
            display: block;
            width: 100%;
            margin: 4mm 0;
            padding: 2mm;
            font-size: 13px;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button type="button" class="print-button no-print" onclick="window.print()">Print</button>

    <h1>{{ $order->order_number }}</h1>
    <div class="center muted">{{ $order->created_at?->format('Y-m-d H:i') }}</div>
    <div class="center">{{ $order->delivery_type->getLabel() }}</div>

    <hr>

    <div><strong>Customer:</strong> {{ $order->user->name }}</div>
    @if ($order->user->phone)
        <div>{{ $order->user->phone }}</div>
    @endif

    @if ($order->delivery_type->value === 'delivery')
        <div><strong>Deliver to:</strong></div>
        <div>{{ $order->delivery_address_line }}</div>
        <div>{{ $order->delivery_city }}</div>
    @endif

    <hr>

    <table>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->quantity }}x {{ $item->product_name }}</td>
                <td class="align-right">{{ number_format($item->line_total_amount / (10 ** \App\Support\Money::decimalsFor($currencyCode)), \App\Support\Money::decimalsFor($currencyCode)) }}</td>
            </tr>
            @foreach ($item->options as $option)
                <tr>
                    <td class="muted">&nbsp;&nbsp;+ {{ $option->option_group_name }}: {{ $option->option_value_name }}</td>
                    <td></td>
                </tr>
            @endforeach
        @endforeach
    </table>

    <hr>

    @php
        $decimals = \App\Support\Money::decimalsFor($currencyCode);
        $format = fn (int $minor) => number_format($minor / (10 ** $decimals), $decimals);
        $taxAmount = $order->total_amount - $order->subtotal_amount + $order->discount_amount - $order->delivery_fee_amount;
    @endphp

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="align-right">{{ $format($order->subtotal_amount) }} {{ $currencyCode }}</td>
        </tr>
        @if ($order->discount_amount > 0)
            <tr>
                <td>Discount @if($order->coupon)({{ $order->coupon->code }})@endif</td>
                <td class="align-right">-{{ $format($order->discount_amount) }} {{ $currencyCode }}</td>
            </tr>
        @endif
        @if ($order->delivery_fee_amount > 0)
            <tr>
                <td>Delivery fee</td>
                <td class="align-right">{{ $format($order->delivery_fee_amount) }} {{ $currencyCode }}</td>
            </tr>
        @endif
        @if ($taxAmount > 0)
            <tr>
                <td>Tax</td>
                <td class="align-right">{{ $format($taxAmount) }} {{ $currencyCode }}</td>
            </tr>
        @endif
        <tr class="grand-total">
            <td>Total</td>
            <td class="align-right">{{ $format($order->total_amount) }} {{ $currencyCode }}</td>
        </tr>
    </table>

    <hr>

    <div>Payment: {{ $order->payment_method->getLabel() }} ({{ $order->payment_status->getLabel() }})</div>

    @if ($order->customer_notes)
        <hr>
        <div><strong>Notes:</strong> {{ $order->customer_notes }}</div>
    @endif

    <hr>
    <div class="center muted">Thank you!</div>
</body>
</html>
