<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminActivityLogs\Tables;

use App\Models\AdminActivityLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdminActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Actor')
                    ->placeholder('System')
                    ->searchable(),
                TextColumn::make('action')
                    ->badge()
                    ->searchable(),
                TextColumn::make('description')
                    ->limit(60)
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->options(fn () => AdminActivityLog::query()
                        ->distinct()
                        ->pluck('action', 'action')
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
