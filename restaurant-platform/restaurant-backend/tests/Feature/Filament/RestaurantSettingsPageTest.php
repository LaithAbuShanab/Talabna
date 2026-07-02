<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\ManageRestaurantSettings;
use App\Models\RestaurantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RestaurantSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_and_manager_can_access_the_settings_page(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get('/admin/manage-restaurant-settings')->assertOk();
        }
    }

    public function test_kitchen_cashier_and_support_cannot_access_the_settings_page(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get('/admin/manage-restaurant-settings')->assertForbidden();
        }
    }

    public function test_saving_updates_the_restaurant_name_and_is_audited(): void
    {
        $admin = User::factory()->superAdmin()->create();
        RestaurantSetting::current();

        Livewire::actingAs($admin)
            ->test(ManageRestaurantSettings::class)
            ->fillForm(['restaurant_name' => 'Updated Restaurant Name'])
            ->call('save');

        $this->assertSame('Updated Restaurant Name', RestaurantSetting::current()->restaurant_name);
        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $admin->id,
            'action' => 'settings.updated',
        ]);
    }

    public function test_the_updated_name_immediately_appears_as_the_panel_brand(): void
    {
        RestaurantSetting::current()->update(['restaurant_name' => 'Brand New Name']);

        $this->get('/admin/login')->assertSee('Brand New Name');
    }
}
