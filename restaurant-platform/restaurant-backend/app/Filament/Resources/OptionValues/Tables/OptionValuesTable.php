<?php

declare(strict_types=1);

namespace App\Filament\Resources\OptionValues\Tables;

use App\Models\OptionGroup;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * No ForceDeleteAction/ForceDeleteBulkAction anywhere: an option value is
 * only ever soft-deleted (see App\Policies\OptionValuePolicy).
 */
class OptionValuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('optionGroup.name')
                    ->label('Option group')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name (English)')
                    ->searchable(),
                TextColumn::make('name_ar')
                    ->label('Name (Arabic)')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('price_delta_amount')
                    ->label('Extra price')
                    ->formatStateUsing(function (int $state): string {
                        $currencyCode = RestaurantSetting::current()->currency_code;

                        return Money::format($state, $currencyCode)['formatted'];
                    })
                    ->sortable(),
                IconColumn::make('is_default')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('option_group_id')
                    ->label('Option group')
                    ->options(fn () => OptionGroup::query()->pluck('name', 'id'))
                    ->searchable(),
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
