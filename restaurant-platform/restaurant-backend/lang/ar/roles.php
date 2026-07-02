<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Role Labels
|--------------------------------------------------------------------------
|
| Human-readable labels for App\Enums\UserRole, used by its getLabel()
| (Filament's HasLabel contract) — shown in the admin panel's role select
| field, table column, and badges. Keep in sync with lang/en/roles.php.
|
*/

return [
    'customer' => 'عميل',
    'super_admin' => 'مدير عام',
    'manager' => 'مدير',
    'kitchen' => 'طاقم المطبخ',
    'cashier' => 'أمين الصندوق',
    'support' => 'الدعم الفني',
];
