<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\RestaurantSetting;
use App\Models\User;
use App\Policies\RestaurantSettingPolicy;
use Tests\TestCase;

class RestaurantSettingPolicyTest extends TestCase
{
    private RestaurantSettingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new RestaurantSettingPolicy;
    }

    private function makeUser(UserRole $role): User
    {
        $user = new User;
        $user->id = 1;
        $user->role = $role;

        return $user;
    }

    public function test_super_admin_and_manager_can_view_and_update(): void
    {
        $settings = new RestaurantSetting;

        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $user = $this->makeUser($role);
            $this->assertTrue($this->policy->view($user));
            $this->assertTrue($this->policy->update($user, $settings));
        }
    }

    public function test_kitchen_cashier_and_support_cannot_view_or_update(): void
    {
        $settings = new RestaurantSetting;

        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $user = $this->makeUser($role);
            $this->assertFalse($this->policy->view($user), "{$role->value} should not be able to view settings");
            $this->assertFalse($this->policy->update($user, $settings), "{$role->value} should not be able to update settings");
        }
    }

    public function test_only_super_admin_can_view_sensitive_settings(): void
    {
        $this->assertTrue($this->policy->viewSensitive($this->makeUser(UserRole::SuperAdmin)));

        foreach ([UserRole::Manager, UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $user = $this->makeUser($role);
            $this->assertFalse($this->policy->viewSensitive($user), "{$role->value} should not be able to view sensitive settings");
        }
    }
}
