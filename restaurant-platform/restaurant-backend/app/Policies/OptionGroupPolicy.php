<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OptionGroup;
use App\Models\User;

/**
 * Guards App\Filament\Resources\OptionGroups\OptionGroupResource. Same
 * super_admin/manager tier as CategoryPolicy. No delete-protection check
 * like Category's is needed here: option_groups' only incoming foreign
 * key (product_option_groups.option_group_id) is cascadeOnDelete, and
 * OptionGroup is soft-deletable, so a normal (soft) delete never touches
 * it — only a real delete would, and forceDelete is always denied below.
 */
class OptionGroupPolicy
{
    private const array MANAGERS = [UserRole::SuperAdmin, UserRole::Manager];

    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::MANAGERS, true);
    }

    public function view(User $user, OptionGroup $optionGroup): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, OptionGroup $optionGroup): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, OptionGroup $optionGroup): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, OptionGroup $optionGroup): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, OptionGroup $optionGroup): bool
    {
        return false;
    }
}
