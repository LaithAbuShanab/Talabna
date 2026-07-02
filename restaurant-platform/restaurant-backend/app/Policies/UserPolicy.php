<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Guards App\Filament\Resources\Users\UserResource — managing *other admin
 * accounts*, not the customer-facing user records the API also stores in
 * this same table (that resource's Eloquent query is scoped to admin
 * roles only). Deliberately tiered rather than "any admin can manage any
 * admin": only super_admin may grant/revoke administrative access at all,
 * since that's the single most sensitive capability in the whole panel.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Manager], true);
    }

    public function view(User $user, User $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function update(User $user, User $model): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    /**
     * Never allow an admin to delete their own account through this
     * resource — that's a self-lockout risk this policy exists to
     * prevent, not just a UI nicety.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->role === UserRole::SuperAdmin && $user->id !== $model->id;
    }
}
