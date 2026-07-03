<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DeliveryZone;
use App\Models\User;

/**
 * Guards App\Filament\Resources\DeliveryZones\DeliveryZoneResource. Same
 * super_admin/manager tier as the rest of this project's admin resources.
 * `forceDelete` always denied: `orders.delivery_zone_id` is `nullOnDelete`,
 * but a zone is still only ever soft-deleted, consistent with every other
 * catalog/pricing entity.
 */
class DeliveryZonePolicy
{
    private const array MANAGERS = [UserRole::SuperAdmin, UserRole::Manager];

    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::MANAGERS, true);
    }

    public function view(User $user, DeliveryZone $deliveryZone): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, DeliveryZone $deliveryZone): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, DeliveryZone $deliveryZone): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, DeliveryZone $deliveryZone): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(User $user, DeliveryZone $deliveryZone): bool
    {
        return false;
    }
}
