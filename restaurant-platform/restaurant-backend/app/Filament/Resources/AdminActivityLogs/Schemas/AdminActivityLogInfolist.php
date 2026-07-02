<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminActivityLogs\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AdminActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->label('When')
                    ->dateTime(),
                TextEntry::make('user.name')
                    ->label('Actor')
                    ->placeholder('System'),
                TextEntry::make('action')
                    ->badge(),
                TextEntry::make('subject_type')
                    ->label('Subject')
                    ->placeholder('-'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                KeyValueEntry::make('metadata')
                    ->placeholder('-')
                    ->columnSpanFull(),
            ]);
    }
}
