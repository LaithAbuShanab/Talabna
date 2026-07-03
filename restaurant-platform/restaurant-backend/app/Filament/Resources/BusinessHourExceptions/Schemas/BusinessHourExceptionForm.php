<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessHourExceptions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * "استثناءات العطل الرسمية" — one row per calendar date that overrides the
 * regular weekly App\Models\BusinessHour schedule (see
 * App\Services\RestaurantAvailabilityService). Deliberately simple: a flat
 * date-keyed form, no recurrence rules.
 */
class BusinessHourExceptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->native(false),
                Toggle::make('is_closed')
                    ->live()
                    ->default(true)
                    ->helperText('Fully closed this date — opening/closing times below are ignored.'),
                Grid::make(2)
                    ->schema([
                        TimePicker::make('opens_at')
                            ->seconds(false)
                            ->required(fn (Get $get): bool => ! $get('is_closed'))
                            ->visible(fn (Get $get): bool => ! $get('is_closed')),
                        TimePicker::make('closes_at')
                            ->seconds(false)
                            ->required(fn (Get $get): bool => ! $get('is_closed'))
                            ->visible(fn (Get $get): bool => ! $get('is_closed'))
                            ->after('opens_at'),
                    ]),
                TextInput::make('note')
                    ->maxLength(255)
                    ->helperText('e.g. "Independence Day" — optional, for admins\' own reference.'),
            ]);
    }
}
