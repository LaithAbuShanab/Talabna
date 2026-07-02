<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * `Customer` is the only non-administrative role — every other case is an
 * internal staff account with access to the Filament admin panel (subject
 * to `User::canAccessPanel()` also requiring `is_active`). Deliberately a
 * single-column enum rather than a dynamic roles/permissions package — see
 * docs/ADMIN_PANEL.md "Why not a permissions package" for the full
 * reasoning. Fine-grained authorization within the admin roles is
 * expressed through ordinary Laravel Policies (App\Policies\*), which
 * check against these cases directly.
 */
enum UserRole: string implements HasColor, HasLabel
{
    case Customer = 'customer';
    case SuperAdmin = 'super_admin';
    case Manager = 'manager';
    case Kitchen = 'kitchen';
    case Cashier = 'cashier';
    case Support = 'support';

    public function isAdmin(): bool
    {
        return $this !== self::Customer;
    }

    /**
     * @return list<self>
     */
    public static function adminCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role->isAdmin()));
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Customer => __('roles.customer'),
            self::SuperAdmin => __('roles.super_admin'),
            self::Manager => __('roles.manager'),
            self::Kitchen => __('roles.kitchen'),
            self::Cashier => __('roles.cashier'),
            self::Support => __('roles.support'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Customer => 'gray',
            self::SuperAdmin => 'danger',
            self::Manager => 'warning',
            self::Kitchen => 'info',
            self::Cashier => 'success',
            self::Support => 'gray',
        };
    }
}
