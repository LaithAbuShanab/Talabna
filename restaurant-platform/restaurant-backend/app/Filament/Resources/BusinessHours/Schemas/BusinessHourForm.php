<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessHours\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * A day can now have more than one row ("أكثر من فترة في اليوم إن لزم" —
 * e.g. lunch + dinner) — business_hours.day_of_week is no longer unique
 * (see the migration), so this is just an ordinary flat CRUD form/table,
 * not a per-day singleton editor.
 */
class BusinessHourForm
{
    private const array DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('day_of_week')
                    ->options(self::DAYS)
                    ->required(),
                Toggle::make('is_closed')
                    ->live()
                    ->default(false)
                    ->helperText('Closed all day — opening/closing times below are ignored.'),
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
            ]);
    }
}
