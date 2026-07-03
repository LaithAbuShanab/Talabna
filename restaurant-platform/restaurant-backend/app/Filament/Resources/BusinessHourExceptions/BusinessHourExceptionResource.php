<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessHourExceptions;

use App\Filament\Resources\BusinessHourExceptions\Pages\CreateBusinessHourException;
use App\Filament\Resources\BusinessHourExceptions\Pages\EditBusinessHourException;
use App\Filament\Resources\BusinessHourExceptions\Pages\ListBusinessHourExceptions;
use App\Filament\Resources\BusinessHourExceptions\Schemas\BusinessHourExceptionForm;
use App\Filament\Resources\BusinessHourExceptions\Tables\BusinessHourExceptionsTable;
use App\Filament\Support\NavigationGroup;
use App\Models\BusinessHourException;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BusinessHourExceptionResource extends Resource
{
    protected static ?string $model = BusinessHourException::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Settings;

    protected static ?string $navigationLabel = 'Holiday Exceptions';

    public static function form(Schema $schema): Schema
    {
        return BusinessHourExceptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BusinessHourExceptionsTable::configure($table);
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
            'index' => ListBusinessHourExceptions::route('/'),
            'create' => CreateBusinessHourException::route('/create'),
            'edit' => EditBusinessHourException::route('/{record}/edit'),
        ];
    }
}
