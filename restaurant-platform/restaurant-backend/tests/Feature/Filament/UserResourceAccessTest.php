<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Authorization for App\Filament\Resources\Users\UserResource, enforced
 * server-side by App\Policies\UserPolicy — not merely by hiding buttons in
 * the UI (see that policy's docblock).
 */
class UserResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_and_manager_can_view_the_users_list(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get('/admin/users')->assertOk();
        }
    }

    public function test_kitchen_cashier_and_support_cannot_view_the_users_list(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get('/admin/users')->assertForbidden();
        }
    }

    public function test_only_super_admin_can_access_the_create_user_page(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $manager = User::factory()->manager()->create();

        $this->actingAs($superAdmin)->get('/admin/users/create')->assertOk();
        $this->actingAs($manager)->get('/admin/users/create')->assertForbidden();
    }

    public function test_only_super_admin_can_access_the_edit_user_page(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $manager = User::factory()->manager()->create();
        $target = User::factory()->kitchen()->create();

        $this->actingAs($superAdmin)->get("/admin/users/{$target->id}/edit")->assertOk();
        $this->actingAs($manager)->get("/admin/users/{$target->id}/edit")->assertForbidden();
    }

    public function test_the_users_list_never_includes_customer_accounts(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $customer = User::factory()->create(['name' => 'A Real Customer']);

        $response = $this->actingAs($superAdmin)->get('/admin/users');

        $response->assertOk()->assertDontSee('A Real Customer');
    }
}
