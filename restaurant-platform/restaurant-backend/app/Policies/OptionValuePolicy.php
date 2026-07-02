<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OptionValue;
use App\Models\User;

/**
 * Guards App\Filament\Resources\OptionValues\OptionValueResource and the
 * Values relation manager on OptionGroupResource. Same super_admin/manager
 * tier as the other menu policies.
 */
class OptionValuePolicy
{
    private const array MANAGERS = [UserRole::SuperAdmin, UserRole::Manager];

    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::MANAGERS, true);
    }

    public function view(User $user, OptionValue $optionValue): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, OptionValue $optionValue): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, OptionValue $optionValue): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, OptionValue $optionValue): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, OptionValue $optionValue): bool
    {
        return false;
    }
}
