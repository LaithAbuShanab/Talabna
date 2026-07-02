<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductImages\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                FileUpload::make('path')
                    ->label('Image')
                    ->image()
                    ->required()
                    ->disk('public')
                    ->directory('products')
                    ->visibility('public'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Toggle::make('is_primary')
                    ->helperText('Only one image per product should be primary — use this product\'s Images tab to keep that consistent automatically.'),
            ]);
    }
}
