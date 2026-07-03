<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\OrderStatus;
use App\Models\RestaurantSetting;
use App\Models\User;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only by construction (TextEntry, no form) — see CustomerResource's
 * docblock for why there is no edit form at all. Never renders `password`
 * or any Sanctum token.
 */
class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $currencyCode = RestaurantSetting::current()->currency_code;

        return $schema
            ->components([
                Section::make('Customer')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('name'),
                                TextEntry::make('email'),
                                TextEntry::make('phone')->placeholder('—'),
                                TextEntry::make('created_at')->label('Joined')->dateTime(),
                                TextEntry::make('is_active')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Blocked')
                                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                                TextEntry::make('blocked_reason')
                                    ->label('Blocked reason')
                                    ->visible(fn (User $record): bool => $record->isBlocked()),
                            ]),
                    ]),

                Section::make('Order activity')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('orders_count')
                                    ->label('Orders placed')
                                    ->getStateUsing(fn (User $record): int => $record->orders()->count()),
                                TextEntry::make('total_spent')
                                    ->label('Total spent')
                                    ->getStateUsing(
                                        fn (User $record): int => (int) $record->orders()
                                            ->where('status', OrderStatus::Delivered)
                                            ->sum('total_amount')
                                    )
                                    ->formatStateUsing(fn (int $state): string => Money::format($state, $currencyCode)['formatted']),
                                TextEntry::make('last_order_at')
                                    ->label('Last order')
                                    ->getStateUsing(fn (User $record): ?string => $record->orders()->max('created_at'))
                                    ->dateTime()
                                    ->placeholder('Never ordered'),
                            ]),
                    ]),
            ]);
    }
}
