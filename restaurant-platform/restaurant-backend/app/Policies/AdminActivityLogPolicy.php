<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AdminActivityLog;
use App\Models\User;

/**
 * Guards App\Filament\Resources\AdminActivityLogs\AdminActivityLogResource
 * — read-only by design (create/update/delete always deny; the resource
 * itself never registers those actions either, but this policy is the
 * authoritative enforcement, not the UI's omission of the buttons).
 */
class AdminActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Manager], true);
    }

    public function view(User $user, AdminActivityLog $adminActivityLog): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AdminActivityLog $adminActivityLog): bool
    {
        return false;
    }

    public function delete(User $user, AdminActivityLog $adminActivityLog): bool
    {
        return false;
    }
}
