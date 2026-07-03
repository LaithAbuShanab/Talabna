<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\User;

/**
 * Guards App\Filament\Resources\Coupons\CouponResource. Same
 * super_admin/manager tier as the rest of the catalog/promotions policies.
 * `forceDelete` always denied: a coupon referenced by past
 * `coupon_usages`/`orders` rows is only ever soft-deleted.
 */
class CouponPolicy
{
    private const array MANAGERS = [UserRole::SuperAdmin, UserRole::Manager];

    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::MANAGERS, true);
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, Coupon $coupon): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, Coupon $coupon): bool
    {
        return false;
    }
}
