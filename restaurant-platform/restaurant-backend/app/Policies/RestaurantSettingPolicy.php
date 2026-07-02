<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RestaurantSetting;
use App\Models\User;

/**
 * Guards App\Filament\Pages\ManageRestaurantSettings. There is only ever
 * one row (RestaurantSetting::current()), so only view/update are
 * meaningful — no create/delete abilities exist for this model.
 */
class RestaurantSettingPolicy
{
    public function view(User $user): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Manager], true);
    }

    public function update(User $user, RestaurantSetting $restaurantSetting): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Manager], true);
    }
}
