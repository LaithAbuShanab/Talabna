<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\AdminActivityLog;
use App\Models\User;
use App\Policies\AdminActivityLogPolicy;
use Tests\TestCase;

class AdminActivityLogPolicyTest extends TestCase
{
    private AdminActivityLogPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new AdminActivityLogPolicy;
    }

    private function makeUser(UserRole $role): User
    {
        $user = new User;
        $user->id = 1;
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
            $this->assertFalse($this->policy->viewAny($this->makeUser($role)));
        }
    }

    public function test_create_update_and_delete_are_always_denied(): void
    {
        $log = new AdminActivityLog;
        $superAdmin = $this->makeUser(UserRole::SuperAdmin);

        $this->assertFalse($this->policy->create($superAdmin));
        $this->assertFalse($this->policy->update($superAdmin, $log));
        $this->assertFalse($this->policy->delete($superAdmin, $log));
    }
}
