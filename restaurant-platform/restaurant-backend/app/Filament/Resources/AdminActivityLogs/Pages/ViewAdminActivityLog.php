<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminActivityLogs\Pages;

use App\Filament\Resources\AdminActivityLogs\AdminActivityLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminActivityLog extends ViewRecord
{
    protected static string $resource = AdminActivityLogResource::class;

    /**
     * No EditAction — read-only (see App\Policies\AdminActivityLogPolicy),
     * and there is deliberately no `edit` route.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
