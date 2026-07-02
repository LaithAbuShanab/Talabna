<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\OptionValues\OptionValueResource;
use App\Filament\Resources\OptionValues\Pages\CreateOptionValue;
use App\Filament\Resources\OptionValues\Pages\EditOptionValue;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the standalone App\Filament\Resources\OptionValues\
 * OptionValueResource (a flat, cross-group view) — the per-group "reorder
 * values" workflow is covered separately by
 * OptionGroupValuesRelationManagerTest.
 */
class OptionValueResourceTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_a_manager_can_create_an_option_value_with_a_major_unit_price(): void
    {
        $group = OptionGroup::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(CreateOptionValue::class)
            ->fillForm([
                'option_group_id' => $group->id,
                'name' => 'Extra Cheese',
                'name_ar' => 'جبنة إضافية',
                'price_delta_amount' => 0.75,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $value = OptionValue::query()->where('name', 'Extra Cheese')->firstOrFail();
        $this->assertSame(750, $value->price_delta_amount);
        $this->assertSame($group->id, $value->option_group_id);
    }

    public function test_option_group_and_name_are_required(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateOptionValue::class)
            ->fillForm(['option_group_id' => null, 'name' => ''])
            ->call('create')
            ->assertHasFormErrors(['option_group_id' => 'required', 'name' => 'required']);
    }

    public function test_a_negative_extra_price_is_rejected(): void
    {
        $group = OptionGroup::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(CreateOptionValue::class)
            ->fillForm([
                'option_group_id' => $group->id,
                'name' => 'Invalid',
                'price_delta_amount' => -0.5,
            ])
            ->call('create')
            ->assertHasFormErrors(['price_delta_amount' => 'min']);

        $this->assertDatabaseMissing('option_values', ['name' => 'Invalid']);
    }

    public function test_a_manager_can_edit_an_option_value(): void
    {
        $value = OptionValue::factory()->create(['name' => 'Old', 'price_delta_amount' => 100]);

        Livewire::actingAs($this->manager())
            ->test(EditOptionValue::class, ['record' => $value->getKey()])
            ->fillForm(['name' => 'New', 'price_delta_amount' => 2])
            ->call('save')
            ->assertHasNoFormErrors();

        $value->refresh();
        $this->assertSame('New', $value->name);
        $this->assertSame(2000, $value->price_delta_amount);
    }

    public function test_an_option_value_can_be_soft_deleted_and_restored(): void
    {
        $value = OptionValue::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditOptionValue::class, ['record' => $value->getKey()])
            ->callAction('delete');

        $this->assertSoftDeleted('option_values', ['id' => $value->id]);

        Livewire::actingAs($this->manager())
            ->test(EditOptionValue::class, ['record' => $value->getKey()])
            ->callAction('restore');

        $this->assertDatabaseHas('option_values', ['id' => $value->id, 'deleted_at' => null]);
    }

    public function test_an_option_value_never_offers_a_force_delete_action(): void
    {
        $value = OptionValue::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditOptionValue::class, ['record' => $value->getKey()])
            ->assertActionDoesNotExist('forceDelete');
    }

    public function test_super_admin_and_manager_can_view_the_option_values_list(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get(OptionValueResource::getUrl('index'))->assertOk();
        }
    }

    public function test_kitchen_cashier_and_support_cannot_view_the_option_values_list(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get(OptionValueResource::getUrl('index'))->assertForbidden();
        }
    }
}
