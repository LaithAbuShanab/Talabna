<?php

declare(strict_types=1);

namespace App\Filament\Resources\OptionValues\Pages;

use App\Filament\Resources\OptionValues\OptionValueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOptionValue extends CreateRecord
{
    protected static string $resource = OptionValueResource::class;
}
