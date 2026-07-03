<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerAddresses\Pages;

use App\Filament\Resources\CustomerAddresses\CustomerAddressResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No CreateAction — read-mostly, see CustomerAddressResource's docblock.
 */
class ListCustomerAddresses extends ListRecords
{
    protected static string $resource = CustomerAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
