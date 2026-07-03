<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessHourExceptions\Pages;

use App\Filament\Resources\BusinessHourExceptions\BusinessHourExceptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBusinessHourException extends CreateRecord
{
    protected static string $resource = BusinessHourExceptionResource::class;
}
