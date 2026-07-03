<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessHourExceptions\Pages;

use App\Filament\Resources\BusinessHourExceptions\BusinessHourExceptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBusinessHourExceptions extends ListRecords
{
    protected static string $resource = BusinessHourExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
