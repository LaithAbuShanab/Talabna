<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminActivityLogs\Pages;

use App\Filament\Resources\AdminActivityLogs\AdminActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminActivityLogs extends ListRecords
{
    protected static string $resource = AdminActivityLogResource::class;

    /**
     * No CreateAction — this resource is read-only (see
     * App\Policies\AdminActivityLogPolicy), and there is deliberately no
     * `create` route to send it to.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
