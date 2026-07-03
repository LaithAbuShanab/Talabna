<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\Actions\OrderStatusActions;
use App\Models\Order;
use App\Models\RestaurantSetting;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "شاشة سريعة وواضحة لإدارة دورة الطلب" list: newest first, polling for
 * near-live updates, every status action available right from the row (in
 * addition to the detail page — see OrderStatusActions's docblock for why
 * both exist), and two purely-visual cues — the status badge itself
 * (Pending is `warning`, the "needs action" color) and a dedicated overdue
 * indicator — since row-level background highlighting would depend on
 * custom CSS the admin panel's own asset pipeline doesn't build (see
 * docs/ADMIN_ORDERS.md for why that was deliberately not attempted).
 */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        $currencyCode = RestaurantSetting::current()->currency_code;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state): string => Money::format($state, $currencyCode)['formatted'])
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Payment method')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Placed')
                    ->since()
                    ->sortable(),
                TextColumn::make('expected_delivery_at')
                    ->label('Expected')
                    ->dateTime('H:i')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_late')
                    ->label('')
                    ->getStateUsing(fn (Order $record): bool => self::isLate($record))
                    ->icon(fn (bool $state): string|BackedEnum|null => $state ? Heroicon::OutlinedExclamationTriangle : null)
                    ->color(fn (bool $state): ?string => $state ? 'danger' : null)
                    ->tooltip(fn (bool $state): ?string => $state ? 'Overdue — past its expected delivery/pickup time' : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::class)
                    ->multiple(),
                SelectFilter::make('payment_status')
                    ->label('Payment status')
                    ->options(PaymentStatus::class)
                    ->multiple(),
                SelectFilter::make('payment_method')
                    ->label('Payment method')
                    ->options(PaymentMethod::class),
                SelectFilter::make('delivery_type')
                    ->label('Type')
                    ->options(DeliveryType::class),
                Filter::make('placed_between')
                    ->label('Date placed')
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('placed_from'),
                            DatePicker::make('placed_until'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['placed_from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['placed_until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    ...OrderStatusActions::all(),
                ]),
            ]);
    }

    public static function isLate(Order $record): bool
    {
        return $record->expected_delivery_at !== null
            && $record->expected_delivery_at->isPast()
            && ! $record->status->isTerminal();
    }
}
