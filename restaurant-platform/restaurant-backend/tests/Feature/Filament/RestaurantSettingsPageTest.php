<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\ManageRestaurantSettings;
use App\Models\RestaurantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    public function test_only_one_settings_row_ever_exists_no_matter_how_often_current_is_called(): void
    {
        RestaurantSetting::current();
        RestaurantSetting::current();
        RestaurantSetting::current();

        $this->assertSame(1, RestaurantSetting::query()->count());
    }

    public function test_manager_cannot_see_the_push_notification_key_field_but_super_admin_can(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $superAdmin = User::factory()->superAdmin()->create();
        RestaurantSetting::current();

        Livewire::actingAs($manager)
            ->test(ManageRestaurantSettings::class)
            ->assertFormFieldDoesNotExist('push_notification_key');

        Livewire::actingAs($superAdmin)
            ->test(ManageRestaurantSettings::class)
            ->assertFormFieldExists('push_notification_key');
    }

    public function test_leaving_the_push_notification_key_blank_preserves_the_existing_value(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $settings = RestaurantSetting::current();
        $settings->forceFill(['push_notification_key' => 'super-secret'])->save();

        Livewire::actingAs($superAdmin)
            ->test(ManageRestaurantSettings::class)
            ->fillForm(['restaurant_name' => 'Something Else'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('super-secret', RestaurantSetting::current()->push_notification_key);
    }

    public function test_setting_the_push_notification_key_stores_it_encrypted(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        RestaurantSetting::current();

        Livewire::actingAs($superAdmin)
            ->test(ManageRestaurantSettings::class)
            ->fillForm(['push_notification_key' => 'brand-new-key'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('brand-new-key', RestaurantSetting::current()->push_notification_key);

        $raw = DB::table('restaurant_settings')->where('id', 1)->value('push_notification_key');
        $this->assertStringNotContainsString('brand-new-key', (string) $raw);
    }

    public function test_logo_upload_rejects_a_disallowed_file_type(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        RestaurantSetting::current();

        Livewire::actingAs($superAdmin)
            ->test(ManageRestaurantSettings::class)
            ->fillForm([
                'logo_path' => [UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')],
            ])
            ->call('save')
            ->assertHasFormErrors(['logo_path']);
    }

    public function test_logo_upload_accepts_an_allowed_image_type(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        RestaurantSetting::current();

        Livewire::actingAs($superAdmin)
            ->test(ManageRestaurantSettings::class)
            ->fillForm([
                'logo_path' => [UploadedFile::fake()->image('logo.png', 100, 100)],
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_enabling_order_email_notifications_requires_valid_email_addresses(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        RestaurantSetting::current();

        Livewire::actingAs($superAdmin)
            ->test(ManageRestaurantSettings::class)
            ->fillForm([
                'notify_new_orders_by_email' => true,
                'order_notification_emails' => 'not-an-email, still-bad',
            ])
            ->call('save')
            ->assertHasFormErrors(['order_notification_emails']);
    }

    public function test_saving_tax_inclusive_and_rate_persists_correctly(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        RestaurantSetting::current();

        Livewire::actingAs($superAdmin)
            ->test(ManageRestaurantSettings::class)
            ->fillForm([
                'is_tax_enabled' => true,
                'tax_rate_bps' => 5,
                'is_tax_inclusive' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = RestaurantSetting::current();
        $this->assertTrue($fresh->is_tax_inclusive);
        $this->assertSame(500, $fresh->tax_rate_bps);
    }
}
