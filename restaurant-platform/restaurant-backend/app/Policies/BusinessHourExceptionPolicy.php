<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\BusinessHourException;
use App\Models\User;

/**
 * Guards App\Filament\Resources\BusinessHourExceptions\
 * BusinessHourExceptionResource. Same super_admin/manager tier and same
 * "plain delete is fine" reasoning as BusinessHourPolicy.
 */
class BusinessHourExceptionPolicy
{
    private const array MANAGERS = [UserRole::SuperAdmin, UserRole::Manager];

    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::MANAGERS, true);
    }

    public function view(User $user, BusinessHourException $businessHourException): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, BusinessHourException $businessHourException): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, BusinessHourException $businessHourException): bool
    {
        return $this->viewAny($user);
    }
}
