<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\OptionGroupsRelationManager;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\Products\RelationManagers\
 * OptionGroupsRelationManager: linking a product to an option group with
 * its per-product is_required/min_select/max_select, min/max validation,
 * and the one-link-per-group-per-product uniqueness rule (see
 * App\Models\ProductOptionGroup).
 */
class ProductOptionGroupsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_a_manager_can_link_an_option_group_to_a_product(): void
    {
        $product = Product::factory()->create();
        $group = OptionGroup::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(OptionGroupsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'option_group_id' => $group->id,
                'is_required' => true,
                'min_select' => 1,
                'max_select' => 1,
                'sort_order' => 0,
            ])
            ->assertHasNoTableActionErrors();

        $link = ProductOptionGroup::query()
            ->where('product_id', $product->id)
            ->where('option_group_id', $group->id)
            ->firstOrFail();

        $this->assertTrue($link->is_required);
        $this->assertSame(1, $link->min_select);
        $this->assertSame(1, $link->max_select);
    }

    public function test_the_same_option_group_cannot_be_linked_twice_to_one_product(): void
    {
        $product = Product::factory()->create();
        $group = OptionGroup::factory()->create();
        ProductOptionGroup::query()->create([
            'product_id' => $product->id,
            'option_group_id' => $group->id,
            'is_required' => false,
        ]);

        Livewire::actingAs($this->manager())
            ->test(OptionGroupsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'option_group_id' => $group->id,
                'is_required' => false,
                'sort_order' => 0,
            ])
            ->assertHasTableActionErrors(['option_group_id' => 'unique']);
    }

    public function test_max_select_must_be_greater_than_or_equal_to_min_select(): void
    {
        $product = Product::factory()->create();
        $group = OptionGroup::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(OptionGroupsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'option_group_id' => $group->id,
                'is_required' => true,
                'min_select' => 3,
                'max_select' => 1,
                'sort_order' => 0,
            ])
            ->assertHasTableActionErrors(['max_select' => 'gte']);
    }

    public function test_an_option_group_is_required_to_link_it(): void
    {
        $product = Product::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(OptionGroupsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: ['option_group_id' => null])
            ->assertHasTableActionErrors(['option_group_id' => 'required']);
    }
}
