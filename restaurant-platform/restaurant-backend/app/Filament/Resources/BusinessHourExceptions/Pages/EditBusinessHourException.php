<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessHourExceptions\Pages;

use App\Filament\Resources\BusinessHourExceptions\BusinessHourExceptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBusinessHourException extends EditRecord
{
    protected static string $resource = BusinessHourExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
