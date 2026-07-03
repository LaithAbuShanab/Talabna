<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerAddresses\Pages;

use App\Filament\Resources\CustomerAddresses\CustomerAddressResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * No EditAction — read-mostly, see CustomerAddressResource's docblock.
 */
class ViewCustomerAddress extends ViewRecord
{
    protected static string $resource = CustomerAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
