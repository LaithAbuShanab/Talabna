<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;

/**
 * Guards App\Filament\Resources\Categories\CategoryResource. Menu/catalog
 * management is restricted to super_admin/manager — the same tier
 * RestaurantSettingPolicy already uses for restaurant configuration;
 * kitchen/cashier/support don't edit the catalog.
 */
class CategoryPolicy
{
    private const array MANAGERS = [UserRole::SuperAdmin, UserRole::Manager];

    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::MANAGERS, true);
    }

    public function view(User $user, Category $category): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Refuses to even offer deletion while the category still has any
     * product (including soft-deleted ones — those still occupy the row
     * `products.category_id` restrictOnDelete() would refuse a *hard*
     * delete for anyway) — this is "منع الأخطاء عند الحذف" (prevent
     * delete errors): the delete action is simply never reachable in that
     * state, rather than surfacing a database constraint error after the
     * fact.
     */
    public function delete(User $user, Category $category): bool
    {
        return $this->viewAny($user) && ! $category->products()->withTrashed()->exists();
    }

    public function restore(User $user, Category $category): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return false;
    }
}
