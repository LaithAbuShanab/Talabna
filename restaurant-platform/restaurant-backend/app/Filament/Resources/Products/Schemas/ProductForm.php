<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Models\RestaurantSetting;
use App\Support\Money;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        $currencyCode = RestaurantSetting::current()->currency_code;

        return $schema
            ->components([
                Section::make('Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Name (English)')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, Set $set, ?string $operation): void {
                                        if ($operation === 'create') {
                                            $set('slug', Str::slug((string) $state));
                                        }
                                    }),
                                TextInput::make('name_ar')
                                    ->label('Name (Arabic)')
                                    ->maxLength(255),
                            ]),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Grid::make(2)
                            ->schema([
                                Textarea::make('description')
                                    ->label('Description (English)')
                                    ->rows(3),
                                Textarea::make('description_ar')
                                    ->label('Description (Arabic)')
                                    ->rows(3),
                            ]),
                    ]),
                Section::make('Pricing & availability')
                    ->schema([
                        TextInput::make('price_amount')
                            ->label('Price')
                            ->numeric()
                            ->required()
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
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('An inactive product is hidden from customers entirely, same as an inactive category.'),
                                Toggle::make('is_available')
                                    ->label('Available')
                                    ->default(true)
                                    ->helperText('Turn off for a temporary stock-out — the product stays visible but cannot be ordered.'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                                TextInput::make('preparation_minutes')
                                    ->label('Estimated preparation time (minutes)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->nullable(),
                            ]),
                    ]),
            ]);
    }
}
