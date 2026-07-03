<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessHours\Pages;

use App\Filament\Resources\BusinessHours\BusinessHourResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBusinessHour extends CreateRecord
{
    protected static string $resource = BusinessHourResource::class;
}
