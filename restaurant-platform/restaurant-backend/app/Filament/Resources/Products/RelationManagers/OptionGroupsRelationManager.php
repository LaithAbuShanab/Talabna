<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\OptionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Bound to Product::productOptionGroups() (a plain HasMany over the
 * App\Models\ProductOptionGroup pivot model) rather than the
 * BelongsToMany optionGroups() relation — see that method's docblock.
 * "ربط مجموعات الإضافات" (linking add-on groups) and "تحديد الحد الأدنى
 * والأقصى للاختيارات" (min/max selections) are both handled by this one
 * ordinary create/edit form.
 */
class OptionGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'productOptionGroups';

    protected static ?string $title = 'Option Groups';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('option_group_id')
                    ->label('Option group')
                    ->options(fn () => OptionGroup::query()->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule) => $rule->where('product_id', $this->getOwnerRecord()->getKey())
                    ),
                Toggle::make('is_required')
                    ->helperText('If off, the customer may skip this group entirely.'),
                TextInput::make('min_select')
                    ->label('Minimum selections')
                    ->numeric()
                    ->minValue(0)
                    ->nullable()
                    ->helperText('Leave blank to fall back to: required groups need at least 1, optional groups need 0.'),
                TextInput::make('max_select')
                    ->label('Maximum selections')
                    ->numeric()
                    ->minValue(1)
                    ->nullable()
                    ->gte('min_select')
                    ->helperText('Leave blank to fall back to: single-choice groups allow 1, multiple-choice groups are unlimited.'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('option_group_id')
            ->modifyQueryUsing(fn ($query) => $query->with('optionGroup'))
            ->columns([
                TextColumn::make('optionGroup.name')
                    ->label('Option group'),
                TextColumn::make('optionGroup.selection_type')
                    ->label('Type')
                    ->badge(),
                IconColumn::make('is_required')
                    ->boolean(),
                TextColumn::make('min_select')
                    ->label('Min')
                    ->placeholder('—'),
                TextColumn::make('max_select')
                    ->label('Max')
                    ->placeholder('—'),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
