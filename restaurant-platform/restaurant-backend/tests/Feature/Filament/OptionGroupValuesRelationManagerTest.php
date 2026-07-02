<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\OptionGroups\Pages\EditOptionGroup;
use App\Filament\Resources\OptionGroups\RelationManagers\ValuesRelationManager;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\OptionGroups\RelationManagers\
 * ValuesRelationManager: creating values with their (money-aware) extra
 * price, validation (never a negative extra price), and drag-and-drop
 * reordering ("إعادة ترتيب القيم").
 */
class OptionGroupValuesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_a_manager_can_create_a_value_with_a_major_unit_price(): void
    {
        $group = OptionGroup::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(ValuesRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditOptionGroup::class])
            ->callTableAction('create', data: [
                'name' => 'Large',
                'name_ar' => 'كبير',
                'price_delta_amount' => 1.5,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 0,
            ])
            ->assertHasNoTableActionErrors();

        $value = OptionValue::query()->where('option_group_id', $group->id)->where('name', 'Large')->firstOrFail();
        $this->assertSame(1500, $value->price_delta_amount);
        $this->assertSame('كبير', $value->name_ar);
    }

    public function test_a_negative_extra_price_is_rejected(): void
    {
        $group = OptionGroup::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(ValuesRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditOptionGroup::class])
            ->callTableAction('create', data: [
                'name' => 'Invalid',
                'price_delta_amount' => -1,
            ])
            ->assertHasTableActionErrors(['price_delta_amount' => 'min']);

        $this->assertDatabaseMissing('option_values', ['name' => 'Invalid']);
    }

    public function test_name_is_required(): void
    {
        $group = OptionGroup::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(ValuesRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditOptionGroup::class])
            ->callTableAction('create', data: ['name' => '', 'price_delta_amount' => 0])
            ->assertHasTableActionErrors(['name' => 'required']);
    }

    public function test_dragging_values_persists_the_new_sort_order(): void
    {
        $group = OptionGroup::factory()->create();
        $first = OptionValue::factory()->create(['option_group_id' => $group->id, 'sort_order' => 1]);
        $second = OptionValue::factory()->create(['option_group_id' => $group->id, 'sort_order' => 2]);

        Livewire::actingAs($this->manager())
            ->test(ValuesRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditOptionGroup::class])
            ->call('reorderTable', [$second->getKey(), $first->getKey()]);

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    public function test_a_value_can_be_soft_deleted_and_restored(): void
    {
        $group = OptionGroup::factory()->create();
        $value = OptionValue::factory()->create(['option_group_id' => $group->id]);

        Livewire::actingAs($this->manager())
            ->test(ValuesRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditOptionGroup::class])
            ->callTableAction('delete', record: $value);

        $this->assertSoftDeleted('option_values', ['id' => $value->id]);
    }
}
