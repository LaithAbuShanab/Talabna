<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers;

use App\Enums\UserRole;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\Schemas\CustomerInfolist;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Filament\Support\NavigationGroup;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Manages *customer accounts only* — the same `users` table
 * App\Filament\Resources\Users\UserResource manages for staff/admin
 * accounts (see that resource's docblock); getEloquentQuery() scopes this
 * one to `role = customer` exclusively.
 *
 * Deliberately no `form()`/`create`/`edit` routes: "عرض بيانات العميل" is
 * a view, not a CRUD form, and the only write actions this task asks for
 * (block/unblock, with a reason) go through
 * App\Filament\Resources\Customers\Actions\CustomerBlockingActions ->
 * App\Services\CustomerBlockingService, never a generic save.
 *
 * Authorization is **not** Gate/Policy-based here on purpose: Laravel only
 * ever registers one policy per Eloquent model, and App\Policies\UserPolicy
 * already is that policy for `User::class`, with a *different* (tighter)
 * `viewAny`/`create`/`update`/`delete` tier for UserResource's admin-account
 * use case. So this Resource overrides the `can*` authorization methods
 * directly instead of relying on Gate resolution — every admin role may
 * view (kitchen/cashier/support all have real reasons to look up a
 * customer), create/update/delete are unconditionally false (see above),
 * and the block/unblock abilities specifically *do* go through
 * `UserPolicy::block()`/`unblock()` (the same registered policy, just
 * different named abilities) since those aren't part of Filament's
 * standard CRUD authorization surface.
 */
class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Customers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', UserRole::Customer);
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->role->isAdmin() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
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
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
