<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Filament\Support\NavigationGroup;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A fast, read-mostly screen for running the order lifecycle
 * (docs/ADMIN_ORDERS.md). Deliberately has no `create`/`edit` routes and no
 * `form()`: an order's items/prices are fixed at checkout
 * (App\Actions\CreateOrderAction) and its status only ever moves through
 * App\Services\OrderStatusTransitionService (see
 * App\Filament\Resources\Orders\Actions\OrderStatusActions) — never via a
 * generic Filament edit form. App\Policies\OrderPolicy denies
 * create/update/delete unconditionally as well, so this isn't just an
 * omitted button.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Orders;

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
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
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
