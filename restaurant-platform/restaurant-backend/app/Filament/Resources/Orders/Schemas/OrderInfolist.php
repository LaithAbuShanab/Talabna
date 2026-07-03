<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\OrderStatusHistory;
use App\Models\RestaurantSetting;
use App\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The order detail page's content. Every amount is rendered through
 * App\Support\Money so it matches the same currency/decimals rules the
 * public API and the admin Product/OptionValue forms already use. Nothing
 * here is editable — Infolist entries are read-only by construction, which
 * is exactly what "منع تعديل العناصر والأسعار بعد إنشاء الطلب" needs: there
 * is no form for a CRUD edit to even target.
 */
class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $currencyCode = RestaurantSetting::current()->currency_code;

        return $schema
            ->components([
                Section::make('Order')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('order_number')
                                    ->label('Order #')
                                    ->weight('bold'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('delivery_type')
                                    ->label('Type')
                                    ->badge(),
                                TextEntry::make('created_at')
                                    ->label('Placed')
                                    ->dateTime(),
                                TextEntry::make('expected_delivery_at')
                                    ->label('Expected delivery/pickup')
                                    ->dateTime()
                                    ->placeholder('—'),
                                TextEntry::make('payment_method')
                                    ->label('Payment method')
                                    ->badge(),
                                TextEntry::make('payment_status')
                                    ->label('Payment status')
                                    ->badge(),
                            ]),
                    ]),

                Section::make('Customer')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('user.name')->label('Name'),
                                TextEntry::make('user.email')->label('Email'),
                                TextEntry::make('user.phone')->label('Phone')->placeholder('—'),
                            ]),
                    ]),

                Section::make('Delivery address')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('delivery_address_line')->label('Address'),
                                TextEntry::make('delivery_city')->label('City'),
                                TextEntry::make('delivery_latitude')->label('Latitude')->placeholder('—'),
                                TextEntry::make('delivery_longitude')->label('Longitude')->placeholder('—'),
                            ]),
                    ])
                    ->visible(fn (Order $record): bool => $record->delivery_type === DeliveryType::Delivery),

                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('product_name')
                                            ->label('Product')
                                            ->weight('bold')
                                            ->columnSpan(2),
                                        TextEntry::make('quantity')
                                            ->label('Qty'),
                                        TextEntry::make('line_total_amount')
                                            ->label('Line total')
                                            ->formatStateUsing(
                                                fn (int $state) => Money::format($state, $currencyCode)['formatted']
                                            ),
                                    ]),
                                RepeatableEntry::make('options')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('option_group_name')
                                            ->label('')
                                            ->formatStateUsing(function (OrderItemOption $record) use ($currencyCode): string {
                                                $extra = $record->price_delta_amount !== 0
                                                    ? ' (+'.Money::format($record->price_delta_amount, $currencyCode)['formatted'].')'
                                                    : '';

                                                return "{$record->option_group_name}: {$record->option_value_name}{$extra}";
                                            }),
                                    ])
                                    ->visible(fn (OrderItem $record): bool => $record->options->isNotEmpty()),
                            ]),
                    ]),

                Section::make('Financial breakdown')
                    ->schema([
                        Grid::make(5)
                            ->schema([
                                TextEntry::make('subtotal_amount')
                                    ->label('Subtotal')
                                    ->formatStateUsing(fn (int $state) => Money::format($state, $currencyCode)['formatted']),
                                TextEntry::make('discount_amount')
                                    ->label('Discount')
                                    ->formatStateUsing(fn (int $state) => Money::format($state, $currencyCode)['formatted']),
                                TextEntry::make('delivery_fee_amount')
                                    ->label('Delivery fee')
                                    ->formatStateUsing(fn (int $state) => Money::format($state, $currencyCode)['formatted']),
                                TextEntry::make('tax_amount')
                                    ->label('Tax (derived)')
                                    ->tooltip('Not a stored column — orders.total_amount already includes tax; this is total - subtotal + discount - delivery_fee, shown for transparency only.')
                                    ->getStateUsing(
                                        fn (Order $record): int => $record->total_amount - $record->subtotal_amount + $record->discount_amount - $record->delivery_fee_amount
                                    )
                                    ->formatStateUsing(fn (int $state) => Money::format($state, $currencyCode)['formatted']),
                                TextEntry::make('total_amount')
                                    ->label('Total')
                                    ->weight('bold')
                                    ->formatStateUsing(fn (int $state) => Money::format($state, $currencyCode)['formatted']),
                            ]),
                        TextEntry::make('coupon.code')
                            ->label('Coupon applied')
                            ->placeholder('—'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('customer_notes')
                            ->label('Customer notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('rejection_reason')
                            ->label('Rejection reason')
                            ->columnSpanFull()
                            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Rejected),
                        TextEntry::make('cancellation_reason')
                            ->label('Cancellation reason')
                            ->columnSpanFull()
                            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Cancelled),
                    ]),

                Section::make('Status timeline')
                    ->schema([
                        RepeatableEntry::make('statusHistories')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('status')
                                            ->label('')
                                            ->badge(),
                                        TextEntry::make('note')
                                            ->label('')
                                            ->placeholder('—')
                                            ->columnSpan(2),
                                        TextEntry::make('changedBy.name')
                                            ->label('')
                                            ->placeholder('System')
                                            ->suffix(fn (OrderStatusHistory $record) => ' • '.$record->created_at?->format('Y-m-d H:i')),
                                    ]),
                            ]),
                    ]),

                Section::make('Payments')
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('method')->label('Method')->badge(),
                                        TextEntry::make('status')->label('Status')->badge(),
                                        TextEntry::make('amount')
                                            ->label('Amount')
                                            ->formatStateUsing(
                                                fn (int $state) => Money::format($state, $currencyCode)['formatted']
                                            ),
                                        TextEntry::make('paid_at')->label('Paid at')->dateTime()->placeholder('—'),
                                    ]),
                                TextEntry::make('transaction_reference')
                                    ->label('Reference')
                                    ->placeholder('—'),
                                TextEntry::make('notes')
                                    ->label('Notes')
                                    ->placeholder('—'),
                            ])
                            ->visible(fn (Order $record): bool => $record->payments->isNotEmpty()),
                    ]),
            ]);
    }
}
