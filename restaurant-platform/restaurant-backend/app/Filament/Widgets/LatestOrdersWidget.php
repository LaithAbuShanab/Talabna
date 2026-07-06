<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\RestaurantSetting;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * "آخر الطلبات" — the last 10 orders overall, always current (not scoped
 * to the Dashboard's period filter, same reasoning as
 * App\Filament\Widgets\OperationalStatusWidget: "latest" is about what
 * just happened, not a historical report). Read-only — no row actions;
 * managing an order's status stays on App\Filament\Resources\Orders\
 * OrderResource, this is just a glance.
 */
class LatestOrdersWidget extends TableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->role->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        $currencyCode = RestaurantSetting::current()->currency_code;

        return $table
            ->heading('Latest orders')
            ->query(fn (): Builder => Order::query()->with('user')->latest()->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('order_number')->label('Order #'),
                TextColumn::make('user.name')->label('Customer'),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment_status')->badge(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state): string => Money::format($state, $currencyCode)['formatted']),
                TextColumn::make('created_at')->label('Placed')->since(),
            ]);
    }
}
