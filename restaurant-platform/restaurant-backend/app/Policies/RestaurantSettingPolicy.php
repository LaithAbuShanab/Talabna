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

    /**
     * "إظهار الإعدادات الحساسة فقط لمن لديه صلاحية" — gates the one
     * genuinely sensitive field on the settings page, `push_notification_key`
     * (see App\Models\RestaurantSetting's `encrypted` cast). Manager can
     * see/edit everything else on the page; only super_admin can see
     * whether a push key is configured or change it.
     */
    public function viewSensitive(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }
}
