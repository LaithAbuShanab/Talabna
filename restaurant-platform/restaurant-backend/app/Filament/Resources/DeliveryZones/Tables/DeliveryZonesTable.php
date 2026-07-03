<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeliveryZones\Tables;

use App\Models\RestaurantSetting;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * No ForceDeleteAction/ForceDeleteBulkAction anywhere: a zone is only ever
 * soft-deleted (see App\Policies\DeliveryZonePolicy).
 */
class DeliveryZonesTable
{
    public static function configure(Table $table): Table
    {
        $currencyCode = RestaurantSetting::current()->currency_code;

        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('delivery_fee_amount')
                    ->label('Fee')
                    ->formatStateUsing(fn (int $state): string => Money::format($state, $currencyCode)['formatted'])
                    ->sortable(),
                TextColumn::make('min_order_amount')
                    ->label('Min. order')
                    ->formatStateUsing(
                        fn (?int $state): string => $state !== null ? Money::format($state, $currencyCode)['formatted'] : '—'
                    )
                    ->toggleable(),
                TextColumn::make('estimated_minutes')
                    ->label('Est. time')
                    ->suffix(' min')
                    ->sortable(),
                TextColumn::make('radius_meters')
                    ->label('Radius')
                    ->suffix(' m')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
