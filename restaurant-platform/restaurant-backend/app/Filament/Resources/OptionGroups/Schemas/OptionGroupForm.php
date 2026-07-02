<?php

declare(strict_types=1);

namespace App\Filament\Resources\OptionGroups\Schemas;

use App\Enums\OptionSelectionType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class OptionGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                Select::make('selection_type')
                    ->label('Selection type')
                    ->options(OptionSelectionType::class)
                    ->required()
                    ->helperText('Single choice: the customer picks one value (e.g. Size). Multiple choice: the customer may pick several (e.g. Toppings). Whether the group is required, and its min/max selections, is set per-product on that product\'s Option Groups tab.'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }
}
