<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\BusinessHour;
use App\Models\User;

/**
 * Guards App\Filament\Resources\BusinessHours\BusinessHourResource. Same
 * super_admin/manager tier. No soft deletes here (see the model) — a
 * business_hours row is config, not user data, and nothing references it
 * by foreign key, so an ordinary delete is fine (e.g. removing an extra
 * "dinner" period, keeping only "lunch," now that multiple periods per day
 * are allowed).
 */
class BusinessHourPolicy
{
    private const array MANAGERS = [UserRole::SuperAdmin, UserRole::Manager];

    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::MANAGERS, true);
    }

    public function view(User $user, BusinessHour $businessHour): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, BusinessHour $businessHour): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, BusinessHour $businessHour): bool
    {
        return $this->viewAny($user);
    }
}
