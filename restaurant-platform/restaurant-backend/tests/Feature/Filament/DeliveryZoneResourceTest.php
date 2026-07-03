<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\DeliveryZones\DeliveryZoneResource;
use App\Filament\Resources\DeliveryZones\Pages\CreateDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\EditDeliveryZone;
use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\DeliveryZones\DeliveryZoneResource:
 * creation, editing, validation, money fields, soft delete/restore, and
 * access control (see App\Policies\DeliveryZonePolicy).
 */
class DeliveryZoneResourceTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
    }

    public function test_a_manager_can_create_a_delivery_zone(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateDeliveryZone::class)
            ->fillForm([
                'name' => 'Downtown',
                'delivery_fee_amount' => 1.5,
                'min_order_amount' => 5,
                'estimated_minutes' => 30,
                'is_active' => true,
                'latitude' => 31.95,
                'longitude' => 35.91,
                'radius_meters' => 3000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $zone = DeliveryZone::query()->where('name', 'Downtown')->firstOrFail();
        $this->assertSame(1500, $zone->delivery_fee_amount);
        $this->assertSame(5000, $zone->min_order_amount);
        $this->assertSame(30, $zone->estimated_minutes);
    }

    public function test_name_delivery_fee_and_estimated_minutes_are_required(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateDeliveryZone::class)
            ->fillForm(['name' => '', 'delivery_fee_amount' => null, 'estimated_minutes' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'delivery_fee_amount' => 'required', 'estimated_minutes' => 'required']);
    }

    public function test_a_negative_delivery_fee_is_rejected(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateDeliveryZone::class)
            ->fillForm([
                'name' => 'Bad Zone',
                'delivery_fee_amount' => -1,
                'estimated_minutes' => 20,
            ])
            ->call('create')
            ->assertHasFormErrors(['delivery_fee_amount' => 'min']);
    }

    public function test_a_manager_can_edit_a_delivery_zone(): void
    {
        $zone = DeliveryZone::factory()->create(['is_active' => true]);

        Livewire::actingAs($this->manager())
            ->test(EditDeliveryZone::class, ['record' => $zone->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($zone->fresh()->is_active);
    }

    public function test_a_zone_can_be_soft_deleted_and_restored(): void
    {
        $zone = DeliveryZone::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditDeliveryZone::class, ['record' => $zone->getKey()])
            ->callAction('delete');

        $this->assertSoftDeleted('delivery_zones', ['id' => $zone->id]);

        Livewire::actingAs($this->manager())
            ->test(EditDeliveryZone::class, ['record' => $zone->getKey()])
            ->callAction('restore');

        $this->assertDatabaseHas('delivery_zones', ['id' => $zone->id, 'deleted_at' => null]);
    }

    public function test_a_zone_never_offers_a_force_delete_action(): void
    {
        $zone = DeliveryZone::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditDeliveryZone::class, ['record' => $zone->getKey()])
            ->assertActionDoesNotExist('forceDelete');
    }

    public function test_super_admin_and_manager_can_view_the_delivery_zones_list(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($admin)->get(DeliveryZoneResource::getUrl('index'))->assertOk();
        }
    }

    public function test_kitchen_cashier_and_support_cannot_view_the_delivery_zones_list(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($admin)->get(DeliveryZoneResource::getUrl('index'))->assertForbidden();
        }
    }
}
