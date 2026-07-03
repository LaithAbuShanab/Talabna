<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerAddresses;

use App\Filament\Resources\CustomerAddresses\Pages\ListCustomerAddresses;
use App\Filament\Resources\CustomerAddresses\Pages\ViewCustomerAddress;
use App\Filament\Resources\CustomerAddresses\Schemas\CustomerAddressInfolist;
use App\Filament\Resources\CustomerAddresses\Tables\CustomerAddressesTable;
use App\Filament\Support\NavigationGroup;
use App\Models\CustomerAddress;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-mostly by design: no `form()`, no `create`/`edit` route at all — an
 * admin can look up a customer's saved addresses (e.g. to help resolve a
 * delivery issue) but never change one. App\Policies\CustomerAddressPolicy
 * grants the admin role `viewAny()`/`view()` but keeps `update()`/
 * `delete()` ownership-only exactly as they already were for the
 * customer-facing API, so even a direct request from an admin can't
 * satisfy them — see that policy's docblock.
 */
class CustomerAddressResource extends Resource
{
    protected static ?string $model = CustomerAddress::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Customers;

    protected static ?string $recordTitleAttribute = 'label';

    public static function infolist(Schema $schema): Schema
    {
        return CustomerAddressInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerAddressesTable::configure($table);
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
            'index' => ListCustomerAddresses::route('/'),
            'view' => ViewCustomerAddress::route('/{record}'),
        ];
    }
}
