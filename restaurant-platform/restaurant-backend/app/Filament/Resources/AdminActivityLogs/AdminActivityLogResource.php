<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminActivityLogs;

use App\Filament\Resources\AdminActivityLogs\Pages\ListAdminActivityLogs;
use App\Filament\Resources\AdminActivityLogs\Pages\ViewAdminActivityLog;
use App\Filament\Resources\AdminActivityLogs\Schemas\AdminActivityLogInfolist;
use App\Filament\Resources\AdminActivityLogs\Tables\AdminActivityLogsTable;
use App\Filament\Support\NavigationGroup;
use App\Models\AdminActivityLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only by design (see App\Policies\AdminActivityLogPolicy, which
 * denies create/update/delete unconditionally) — only `index`/`view` pages
 * exist; there is deliberately no `create`/`edit` route to remove buttons
 * from either, so the policy is the actual enforcement, not just an
 * omitted button.
 */
class AdminActivityLogResource extends Resource
{
    protected static ?string $model = AdminActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?string $navigationLabel = 'Activity Log';

    public static function infolist(Schema $schema): Schema
    {
        return AdminActivityLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminActivityLogsTable::configure($table);
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
            'index' => ListAdminActivityLogs::route('/'),
            'view' => ViewAdminActivityLog::route('/{record}'),
        ];
    }
}
