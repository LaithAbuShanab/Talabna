<?php

declare(strict_types=1);

namespace App\Filament\Resources\OptionValues\Schemas;

use App\Models\RestaurantSetting;
use App\Support\Money;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class OptionValueForm
{
    public static function configure(Schema $schema): Schema
    {
        $currencyCode = RestaurantSetting::current()->currency_code;

        return $schema
            ->components([
                Select::make('option_group_id')
                    ->label('Option group')
                    ->relationship('optionGroup', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Grid::make(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name (English)')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_ar')
                            ->label('Name (Arabic)')
                            ->maxLength(255),
                    ]),
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
                Grid::make(2)
                    ->schema([
                        Toggle::make('is_default')
                            ->label('Selected by default')
                            ->default(false),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }
}
