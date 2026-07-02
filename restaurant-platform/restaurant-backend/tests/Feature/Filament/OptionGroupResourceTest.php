<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\OptionSelectionType;
use App\Enums\UserRole;
use App\Filament\Resources\OptionGroups\OptionGroupResource;
use App\Filament\Resources\OptionGroups\Pages\CreateOptionGroup;
use App\Filament\Resources\OptionGroups\Pages\EditOptionGroup;
use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\OptionGroups\OptionGroupResource: creation,
 * editing, validation, soft delete/restore, and access control (see
 * App\Policies\OptionGroupPolicy).
 */
class OptionGroupResourceTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_a_manager_can_create_a_single_choice_option_group(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateOptionGroup::class)
            ->fillForm([
                'name' => 'Size',
                'name_ar' => 'الحجم',
                'selection_type' => OptionSelectionType::Single->value,
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $group = OptionGroup::query()->where('name', 'Size')->firstOrFail();
        $this->assertSame(OptionSelectionType::Single, $group->selection_type);
        $this->assertSame('الحجم', $group->name_ar);
    }

    public function test_name_and_selection_type_are_required(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateOptionGroup::class)
            ->fillForm(['name' => '', 'selection_type' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'selection_type' => 'required']);
    }

    public function test_a_manager_can_edit_an_option_group(): void
    {
        $group = OptionGroup::factory()->single()->create(['name' => 'Old']);

        Livewire::actingAs($this->manager())
            ->test(EditOptionGroup::class, ['record' => $group->getKey()])
            ->fillForm(['name' => 'New', 'selection_type' => OptionSelectionType::Multiple->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $group->refresh();
        $this->assertSame('New', $group->name);
        $this->assertSame(OptionSelectionType::Multiple, $group->selection_type);
    }

    public function test_an_option_group_can_be_soft_deleted_and_restored(): void
    {
        $group = OptionGroup::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditOptionGroup::class, ['record' => $group->getKey()])
            ->callAction('delete');

        $this->assertSoftDeleted('option_groups', ['id' => $group->id]);

        Livewire::actingAs($this->manager())
            ->test(EditOptionGroup::class, ['record' => $group->getKey()])
            ->callAction('restore');

        $this->assertDatabaseHas('option_groups', ['id' => $group->id, 'deleted_at' => null]);
    }

    public function test_an_option_group_never_offers_a_force_delete_action(): void
    {
        $group = OptionGroup::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditOptionGroup::class, ['record' => $group->getKey()])
            ->assertActionDoesNotExist('forceDelete');
    }

    public function test_super_admin_and_manager_can_view_the_option_groups_list(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get(OptionGroupResource::getUrl('index'))->assertOk();
        }
    }

    public function test_kitchen_cashier_and_support_cannot_view_the_option_groups_list(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get(OptionGroupResource::getUrl('index'))->assertForbidden();
        }
    }
}
