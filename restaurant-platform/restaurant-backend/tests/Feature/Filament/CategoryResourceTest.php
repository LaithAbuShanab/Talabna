<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\Categories\CategoryResource: creation,
 * editing, validation, drag-and-drop reordering, the delete-protection
 * policy rule, soft delete/restore, and access control (see
 * App\Policies\CategoryPolicy).
 */
class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_a_manager_can_create_a_category(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateCategory::class)
            ->fillForm([
                'name' => 'Beverages',
                'name_ar' => 'مشروبات',
                'slug' => 'beverages',
                'description' => 'Cold and hot drinks',
                'sort_order' => 5,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::query()->where('slug', 'beverages')->firstOrFail();
        $this->assertSame('Beverages', $category->name);
        $this->assertSame('مشروبات', $category->name_ar);
        $this->assertSame(5, $category->sort_order);
        $this->assertTrue($category->is_active);
    }

    public function test_name_and_slug_are_required_and_slug_must_be_unique(): void
    {
        Category::factory()->create(['slug' => 'desserts']);

        Livewire::actingAs($this->manager())
            ->test(CreateCategory::class)
            ->fillForm(['name' => '', 'slug' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'slug' => 'required']);

        Livewire::actingAs($this->manager())
            ->test(CreateCategory::class)
            ->fillForm(['name' => 'Sweets', 'slug' => 'desserts'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_a_manager_can_edit_a_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name', 'is_active' => true]);

        Livewire::actingAs($this->manager())
            ->test(EditCategory::class, ['record' => $category->getKey()])
            ->fillForm(['name' => 'New Name', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $category->refresh();
        $this->assertSame('New Name', $category->name);
        $this->assertFalse($category->is_active);
    }

    public function test_dragging_categories_persists_the_new_sort_order(): void
    {
        $first = Category::factory()->create(['sort_order' => 1]);
        $second = Category::factory()->create(['sort_order' => 2]);

        Livewire::actingAs($this->manager())
            ->test(ListCategories::class)
            ->call('reorderTable', [$second->getKey(), $first->getKey()]);

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        Livewire::actingAs($this->manager())
            ->test(EditCategory::class, ['record' => $category->getKey()])
            ->assertActionHidden('delete');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_an_empty_category_can_be_soft_deleted_and_restored(): void
    {
        $category = Category::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditCategory::class, ['record' => $category->getKey()])
            ->callAction('delete');

        $this->assertSoftDeleted('categories', ['id' => $category->id]);

        Livewire::actingAs($this->manager())
            ->test(EditCategory::class, ['record' => $category->getKey()])
            ->callAction('restore');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_super_admin_and_manager_can_view_the_categories_list(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get(CategoryResource::getUrl('index'))->assertOk();
        }
    }

    public function test_kitchen_cashier_and_support_cannot_view_the_categories_list(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get(CategoryResource::getUrl('index'))->assertForbidden();
        }
    }
}
