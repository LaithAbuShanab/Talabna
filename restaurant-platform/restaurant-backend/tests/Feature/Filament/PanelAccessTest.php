<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\RestaurantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "دخول اللوحة" (panel login/access) — every admin role can reach the
 * dashboard, an ordinary customer never can, and a deactivated admin
 * account is blocked regardless of role.
 */
class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_role_can_access_the_dashboard(): void
    {
        foreach (UserRole::adminCases() as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get('/admin')->assertOk();
        }
    }

    public function test_a_customer_cannot_access_the_admin_panel(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_a_deactivated_admin_cannot_access_the_panel_regardless_of_role(): void
    {
        foreach (UserRole::adminCases() as $role) {
            $admin = User::factory()->create(['role' => $role, 'is_active' => false]);

            $this->actingAs($admin)->get('/admin')->assertForbidden();
        }
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_login_page_renders_in_arabic_and_rtl_by_default(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="ar"', false);
    }

    public function test_login_page_shows_the_restaurant_name_as_the_panel_brand(): void
    {
        RestaurantSetting::current()->update(['restaurant_name' => 'My Test Restaurant']);

        $this->get('/admin/login')->assertSee('My Test Restaurant');
    }
}
