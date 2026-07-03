<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessHours\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessHoursTable
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

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('day_of_week')
                    ->label('Day')
                    ->formatStateUsing(fn (int $state): string => self::DAYS[$state] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('opens_at')
                    ->time('H:i')
                    ->placeholder('—'),
                TextColumn::make('closes_at')
                    ->time('H:i')
                    ->placeholder('—'),
                IconColumn::make('is_closed')
                    ->label('Closed')
                    ->boolean(),
            ])
            ->defaultSort('day_of_week')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
