<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Filament\Support\NavigationGroup;
use App\Models\User;
use App\Services\AdminActivityLogger;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Manages *staff/admin accounts only* — the same `users` table also holds
 * ordinary customers (see docs/DATABASE_SCHEMA.md), so getEloquentQuery()
 * scopes this resource to admin roles exclusively; a customer record can
 * never appear, be edited, or be deleted here. Authorization beyond that
 * scoping (who may view/create/update/delete an admin account) is
 * App\Policies\UserPolicy's job — auto-discovered by Laravel's
 * App\Models\X -> App\Policies\XPolicy convention, not registered here.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('role', UserRole::adminCases());
    }

    /**
     * Shared by the row-level and edit-page DeleteAction instances (see
     * Tables\UsersTable and Pages\EditUser) so an admin account's removal
     * is always recorded, regardless of which button triggered it.
     */
    public static function logDeletion(User $record): void
    {
        app(AdminActivityLogger::class)->log(
            actor: Auth::user(),
            action: 'user.deleted',
            subject: null,
            description: "Deleted admin account {$record->email} (role: {$record->role->value}).",
        );
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
