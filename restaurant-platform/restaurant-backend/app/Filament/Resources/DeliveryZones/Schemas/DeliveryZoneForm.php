<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeliveryZones\Schemas;

use App\Models\RestaurantSetting;
use App\Support\Money;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * `latitude`/`longitude`/`radius_meters` are a plain center-point + radius —
 * deliberately not a polygon/map-picker UI ("polygon أو geospatial mapping
 * ليس مطلوبًا في النسخة الأولى؛ استخدم مناطق معرفة إداريًا بطريقة بسيطة"):
 * three ordinary numeric inputs, exactly matching how
 * App\Http\Controllers\Api\V1\DeliveryZoneController::check() already
 * matches a coordinate against a zone (Haversine distance vs. radius).
 */
class DeliveryZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        $currencyCode = RestaurantSetting::current()->currency_code;

        return $schema
            ->components([
                Section::make('Zone')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('delivery_fee_amount')
                                    ->label('Delivery fee')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->prefix($currencyCode)
                                    ->step(1 / (10 ** Money::decimalsFor($currencyCode)))
                                    ->formatStateUsing(
                                        fn (?int $state): ?float => $state !== null ? Money::toMajorUnits($state, $currencyCode) : null
                                    )
                                    ->dehydrateStateUsing(
                                        fn ($state): int => Money::toMinorUnits((float) $state, $currencyCode)
                                    ),
                                TextInput::make('min_order_amount')
                                    ->label('Minimum order amount')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix($currencyCode)
                                    ->step(1 / (10 ** Money::decimalsFor($currencyCode)))
                                    ->formatStateUsing(
                                        fn (?int $state): ?float => $state !== null ? Money::toMajorUnits($state, $currencyCode) : null
                                    )
                                    ->dehydrateStateUsing(
                                        fn ($state): ?int => $state !== null && $state !== '' ? Money::toMinorUnits((float) $state, $currencyCode) : null
                                    )
                                    ->helperText('Leave blank for no minimum specific to this zone.'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('estimated_minutes')
                                    ->label('Estimated delivery time (minutes)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),
                Section::make('Coverage area')
                    ->description('A simple center point + radius — no map/polygon drawing in this version.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('latitude')
                                    ->numeric()
                                    ->minValue(-90)
                                    ->maxValue(90),
                                TextInput::make('longitude')
                                    ->numeric()
                                    ->minValue(-180)
                                    ->maxValue(180),
                                TextInput::make('radius_meters')
                                    ->label('Radius (meters)')
                                    ->numeric()
                                    ->minValue(1),
                            ]),
                    ]),
            ]);
    }
}
