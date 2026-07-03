<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\Actions\CustomerBlockingActions;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * No EditAction — see CustomerResource's docblock. Block/unblock are the
 * only write actions this page offers.
 */
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return CustomerBlockingActions::all();
    }
}
