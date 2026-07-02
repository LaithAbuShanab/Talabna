<?php

declare(strict_types=1);

namespace App\Filament\Resources\OptionGroups\Pages;

use App\Filament\Resources\OptionGroups\OptionGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditOptionGroup extends EditRecord
{
    protected static string $resource = OptionGroupResource::class;

    /**
     * No ForceDeleteAction — an option group is only ever soft-deleted (see
     * App\Policies\OptionGroupPolicy).
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
