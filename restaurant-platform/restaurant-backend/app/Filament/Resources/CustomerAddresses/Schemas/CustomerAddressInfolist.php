<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerAddresses\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CustomerAddressInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('user.name')->label('Customer'),
                        TextEntry::make('label'),
                        TextEntry::make('address_line1')->label('Address line 1'),
                        TextEntry::make('address_line2')->label('Address line 2')->placeholder('—'),
                        TextEntry::make('city'),
                        TextEntry::make('is_default')
                            ->label('Default address')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('latitude')->placeholder('—'),
                        TextEntry::make('longitude')->placeholder('—'),
                        TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
