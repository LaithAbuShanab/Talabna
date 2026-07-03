<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\Actions\OrderStatusActions;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * No EditAction — see OrderResource's docblock. Every status action from
 * OrderStatusActions is offered here too (in addition to the list's row
 * actions), since this is where "معلومات العميل"/"snapshot العنوان"/
 * "العناصر والإضافات"/etc. are all visible at once — the natural place to
 * decide what to do next with the order.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * The list table only eager-loads `user` (see OrdersTable) — this page
     * needs the full graph once, since every Infolist section reads from a
     * different relation.
     */
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load([
            'user',
            'coupon',
            'deliveryZone',
            'customerAddress',
            'items.options',
            'statusHistories.changedBy',
            'payments',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('admin.orders.print', ['order' => $this->getRecord()]))
                ->openUrlInNewTab(),
            ...OrderStatusActions::all(),
        ];
    }
}
