<?php

declare(strict_types=1);

namespace App\Filament\Resources\OptionGroups\Pages;

use App\Filament\Resources\OptionGroups\OptionGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOptionGroup extends CreateRecord
{
    protected static string $resource = OptionGroupResource::class;
}
