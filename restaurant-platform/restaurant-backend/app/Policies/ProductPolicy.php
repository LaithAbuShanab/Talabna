<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;

/**
 * Guards App\Filament\Resources\Products\ProductResource. Same
 * super_admin/manager tier as CategoryPolicy — see its docblock.
 * forceDelete is always denied: a product referenced by a past order must
 * never be permanently removed (order_items.product_id is nullOnDelete,
 * which only fires on a real delete — soft delete, the only kind this
 * resource ever performs, never touches it at all).
 */
class ProductPolicy
{
    private const array MANAGERS = [UserRole::SuperAdmin, UserRole::Manager];

    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::MANAGERS, true);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return false;
    }
}
