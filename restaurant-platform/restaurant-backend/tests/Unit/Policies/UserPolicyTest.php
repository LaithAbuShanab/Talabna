<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new UserPolicy;
    }

    private function makeUser(UserRole $role, int $id = 1): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = $role;

        return $user;
    }

    public function test_super_admin_and_manager_can_view_any(): void
    {
        $this->assertTrue($this->policy->viewAny($this->makeUser(UserRole::SuperAdmin)));
        $this->assertTrue($this->policy->viewAny($this->makeUser(UserRole::Manager)));
    }

    public function test_kitchen_cashier_and_support_cannot_view_any(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $this->assertFalse($this->policy->viewAny($this->makeUser($role)), "{$role->value} should not be able to view admin users");
        }
    }

    public function test_only_super_admin_can_create(): void
    {
        $this->assertTrue($this->policy->create($this->makeUser(UserRole::SuperAdmin)));
        $this->assertFalse($this->policy->create($this->makeUser(UserRole::Manager)));
    }

    public function test_only_super_admin_can_update(): void
    {
        $target = $this->makeUser(UserRole::Kitchen, id: 2);

        $this->assertTrue($this->policy->update($this->makeUser(UserRole::SuperAdmin), $target));
        $this->assertFalse($this->policy->update($this->makeUser(UserRole::Manager), $target));
    }

    public function test_super_admin_can_delete_another_admin(): void
    {
        $superAdmin = $this->makeUser(UserRole::SuperAdmin, id: 1);
        $target = $this->makeUser(UserRole::Kitchen, id: 2);

        $this->assertTrue($this->policy->delete($superAdmin, $target));
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        $superAdmin = $this->makeUser(UserRole::SuperAdmin, id: 1);

        $this->assertFalse($this->policy->delete($superAdmin, $superAdmin));
    }

    public function test_super_admin_and_manager_can_block_and_unblock_a_customer(): void
    {
        $customer = $this->makeUser(UserRole::Customer, id: 2);

        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = $this->makeUser($role);
            $this->assertTrue($this->policy->block($admin, $customer), "{$role->value} should be able to block a customer");
            $this->assertTrue($this->policy->unblock($admin, $customer), "{$role->value} should be able to unblock a customer");
        }
    }

    public function test_kitchen_cashier_and_support_cannot_block_or_unblock_a_customer(): void
    {
        $customer = $this->makeUser(UserRole::Customer, id: 2);

        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = $this->makeUser($role);
            $this->assertFalse($this->policy->block($admin, $customer), "{$role->value} should not be able to block a customer");
            $this->assertFalse($this->policy->unblock($admin, $customer), "{$role->value} should not be able to unblock a customer");
        }
    }
}
