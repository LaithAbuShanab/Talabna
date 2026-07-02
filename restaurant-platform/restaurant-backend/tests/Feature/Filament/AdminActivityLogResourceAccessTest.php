<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActivityLogResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_and_manager_can_view_the_activity_log(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get('/admin/admin-activity-logs')->assertOk();
        }
    }

    public function test_kitchen_cashier_and_support_cannot_view_the_activity_log(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get('/admin/admin-activity-logs')->assertForbidden();
        }
    }

    public function test_there_is_no_create_route(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/admin-activity-logs/create')->assertNotFound();
    }

    public function test_a_log_entry_can_be_viewed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $log = AdminActivityLog::factory()->create();

        $this->actingAs($admin)->get("/admin/admin-activity-logs/{$log->id}")->assertOk();
    }
}
