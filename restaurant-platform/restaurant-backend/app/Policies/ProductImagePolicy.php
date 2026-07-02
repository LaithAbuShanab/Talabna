<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ProductImage;
use App\Models\User;

/**
 * Guards App\Filament\Resources\ProductImages\ProductImageResource and the
 * Images relation manager on ProductResource. Same super_admin/manager
 * tier as CategoryPolicy/ProductPolicy. ProductImage isn't soft-deletable
 * (see its model) — a removed image is just a stale file reference, not
 * something an old order snapshot ever points back to.
 */
class ProductImagePolicy
{
    private const array MANAGERS = [UserRole::SuperAdmin, UserRole::Manager];

    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::MANAGERS, true);
    }

    public function view(User $user, ProductImage $productImage): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ProductImage $productImage): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ProductImage $productImage): bool
    {
        return $this->viewAny($user);
    }
}
