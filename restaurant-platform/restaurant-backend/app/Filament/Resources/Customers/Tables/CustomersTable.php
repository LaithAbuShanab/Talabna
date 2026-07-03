<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Tables;

use App\Enums\OrderStatus;
use App\Filament\Resources\Customers\Actions\CustomerBlockingActions;
use App\Models\RestaurantSetting;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "إجمالي إنفاقه" only counts `delivered` orders — a pending/cancelled
 * order was never actually paid for, so counting it as "spend" would
 * overstate a customer's real value. "آخر طلب" is simply the most recent
 * order regardless of status (a customer's last *attempt*, not just their
 * last completed one — more useful for support/kitchen context). Neither
 * a password nor any Sanctum token ever appears in a column here — see
 * docs/ADMIN_CUSTOMERS.md.
 */
class CustomersTable
{
    public static function configure(Table $table): Table
    {
        $currencyCode = RestaurantSetting::current()->currency_code;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount('orders')
                ->withSum(['orders as total_spent' => fn (Builder $q) => $q->where('status', OrderStatus::Delivered)], 'total_amount')
                ->withMax('orders as last_order_at', 'created_at'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->sortable(),
                TextColumn::make('total_spent')
                    ->label('Total spent')
                    ->formatStateUsing(fn (?int $state): string => Money::format($state ?? 0, $currencyCode)['formatted'])
                    ->sortable(),
                TextColumn::make('last_order_at')
                    ->label('Last order')
                    ->dateTime()
                    ->placeholder('Never ordered')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Blocked')
                    ->placeholder('All'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    ...CustomerBlockingActions::all(),
                ]),
            ]);
    }
}
