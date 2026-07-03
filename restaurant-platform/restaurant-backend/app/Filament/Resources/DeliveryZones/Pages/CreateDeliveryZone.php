<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeliveryZones\Pages;

use App\Filament\Resources\DeliveryZones\DeliveryZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliveryZone extends CreateRecord
{
    protected static string $resource = DeliveryZoneResource::class;
}
