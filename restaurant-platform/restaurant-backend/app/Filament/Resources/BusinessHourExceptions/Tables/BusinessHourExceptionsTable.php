<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessHourExceptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessHourExceptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_closed')
                    ->label('Fully closed')
                    ->boolean(),
                TextColumn::make('opens_at')
                    ->time('H:i')
                    ->placeholder('—'),
                TextColumn::make('closes_at')
                    ->time('H:i')
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->searchable()
                    ->placeholder('—'),
            ])
            ->defaultSort('date', 'desc')
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
