<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Role Labels
|--------------------------------------------------------------------------
|
| Human-readable labels for App\Enums\UserRole, used by its getLabel()
| (Filament's HasLabel contract) — shown in the admin panel's role select
| field, table column, and badges. Keep in sync with lang/ar/roles.php.
|
*/

return [
    'customer' => 'Customer',
    'super_admin' => 'Super Admin',
    'manager' => 'Manager',
    'kitchen' => 'Kitchen Staff',
    'cashier' => 'Cashier',
    'support' => 'Support',
];
