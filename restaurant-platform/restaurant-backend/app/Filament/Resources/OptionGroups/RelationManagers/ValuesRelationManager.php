<?php

declare(strict_types=1);

namespace App\Filament\Resources\OptionGroups\RelationManagers;

use App\Models\RestaurantSetting;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * `->reorderable('sort_order')` gives the explicitly requested "إعادة ترتيب
 * القيم" (reorder values) drag-and-drop. price_delta_amount follows the same
 * money major/minor-unit form pattern as ProductForm's price_amount — never
 * negative, since an option value can only add cost, not discount it.
 */
class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $title = 'Values';

    public function form(Schema $schema): Schema
    {
        $currencyCode = RestaurantSetting::current()->currency_code;

        return $schema->components([
            TextInput::make('name')
                ->label('Name (English)')
                ->required()
                ->maxLength(255),
            TextInput::make('name_ar')
                ->label('Name (Arabic)')
                ->maxLength(255),
            TextInput::make('price_delta_amount')
                ->label('Extra price')
                ->numeric()
                ->required()
                ->default(0)
                ->minValue(0)
                ->step(1 / (10 ** Money::decimalsFor($currencyCode)))
                ->prefix($currencyCode)
                ->live(onBlur: true)
                ->formatStateUsing(
                    fn (?int $state): ?float => $state !== null ? Money::toMajorUnits($state, $currencyCode) : null
                )
                ->dehydrateStateUsing(
                    fn ($state): int => Money::toMinorUnits((float) $state, $currencyCode)
                )
                ->helperText(function ($state) use ($currencyCode): ?string {
                    if ($state === null || $state === '') {
                        return null;
                    }

                    $minorUnits = Money::toMinorUnits((float) $state, $currencyCode);

                    return "Stored as {$minorUnits} (smallest {$currencyCode} unit) — never negative.";
                }),
            Toggle::make('is_default')
                ->label('Selected by default')
                ->default(false),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->minValue(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
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
                    }),
                IconColumn::make('is_default')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make(),
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
