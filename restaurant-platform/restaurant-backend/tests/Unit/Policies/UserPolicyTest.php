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
}
