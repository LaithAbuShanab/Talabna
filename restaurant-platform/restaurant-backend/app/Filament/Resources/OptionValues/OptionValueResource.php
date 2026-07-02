<?php

declare(strict_types=1);

namespace App\Filament\Resources\OptionValues;

use App\Filament\Resources\OptionValues\Pages\CreateOptionValue;
use App\Filament\Resources\OptionValues\Pages\EditOptionValue;
use App\Filament\Resources\OptionValues\Pages\ListOptionValues;
use App\Filament\Resources\OptionValues\Schemas\OptionValueForm;
use App\Filament\Resources\OptionValues\Tables\OptionValuesTable;
use App\Filament\Support\NavigationGroup;
use App\Models\OptionValue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * A flat, cross-group view of every value (e.g. for finding an orphaned or
 * duplicate value) — the per-group "reorder values" workflow lives on
 * App\Filament\Resources\OptionGroups\RelationManagers\ValuesRelationManager
 * instead, which is where that's actually convenient to manage.
 */
class OptionValueResource extends Resource
{
    protected static ?string $model = OptionValue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Menu;

    public static function form(Schema $schema): Schema
    {
        return OptionValueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OptionValuesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOptionValues::route('/'),
            'create' => CreateOptionValue::route('/create'),
            'edit' => EditOptionValue::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
