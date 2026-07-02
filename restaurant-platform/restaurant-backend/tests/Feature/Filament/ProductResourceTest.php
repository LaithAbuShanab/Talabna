<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\Products\ProductResource: creation, editing,
 * the major/minor-unit price preview (see App\Support\Money), validation
 * (including "never a negative price"), bulk enable/disable, soft
 * delete/restore, and access control (see App\Policies\ProductPolicy). The
 * restaurant's default currency is JOD (3 decimal places) — see
 * App\Models\RestaurantSetting::current().
 */
class ProductResourceTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_a_manager_can_create_a_product_with_a_major_unit_price(): void
    {
        $category = Category::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(CreateProduct::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Cheeseburger',
                'name_ar' => 'تشيز برجر',
                'slug' => 'cheeseburger',
                'description' => 'A juicy burger',
                'price_amount' => 2.5,
                'is_active' => true,
                'is_available' => true,
                'sort_order' => 1,
                'preparation_minutes' => 15,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', 'cheeseburger')->firstOrFail();
        $this->assertSame(2500, $product->price_amount);
        $this->assertSame(15, $product->preparation_minutes);
        $this->assertTrue($product->is_active);
    }

    public function test_required_fields_and_category_are_validated(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateProduct::class)
            ->fillForm(['name' => '', 'slug' => '', 'category_id' => null, 'price_amount' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'slug' => 'required', 'category_id' => 'required', 'price_amount' => 'required']);
    }

    public function test_a_negative_price_is_rejected(): void
    {
        $category = Category::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(CreateProduct::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Discount Item',
                'slug' => 'discount-item',
                'price_amount' => -1,
            ])
            ->call('create')
            ->assertHasFormErrors(['price_amount' => 'min']);

        $this->assertDatabaseMissing('products', ['slug' => 'discount-item']);
    }

    public function test_a_manager_can_edit_a_product(): void
    {
        $product = Product::factory()->create(['name' => 'Old Name', 'price_amount' => 1000]);

        Livewire::actingAs($this->manager())
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['name' => 'New Name', 'price_amount' => 3.75])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertSame('New Name', $product->name);
        $this->assertSame(3750, $product->price_amount);
    }

    public function test_bulk_enable_and_disable_actions_toggle_is_active(): void
    {
        $active = Product::factory()->create(['is_active' => true]);
        $alsoActive = Product::factory()->create(['is_active' => true]);

        Livewire::actingAs($this->manager())
            ->test(ListProducts::class)
            ->callTableBulkAction('disable', [$active->getKey(), $alsoActive->getKey()])
            ->assertHasNoTableBulkActionErrors();

        $this->assertFalse($active->fresh()->is_active);
        $this->assertFalse($alsoActive->fresh()->is_active);

        Livewire::actingAs($this->manager())
            ->test(ListProducts::class)
            ->callTableBulkAction('enable', [$active->getKey(), $alsoActive->getKey()])
            ->assertHasNoTableBulkActionErrors();

        $this->assertTrue($active->fresh()->is_active);
        $this->assertTrue($alsoActive->fresh()->is_active);
    }

    public function test_a_product_can_be_soft_deleted_and_restored(): void
    {
        $product = Product::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->callAction('delete');

        $this->assertSoftDeleted('products', ['id' => $product->id]);

        Livewire::actingAs($this->manager())
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->callAction('restore');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    public function test_a_product_never_offers_a_force_delete_action(): void
    {
        $product = Product::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->assertActionDoesNotExist('forceDelete');
    }

    public function test_super_admin_and_manager_can_view_the_products_list(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get(ProductResource::getUrl('index'))->assertOk();
        }
    }

    public function test_kitchen_cashier_and_support_cannot_view_the_products_list(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get(ProductResource::getUrl('index'))->assertForbidden();
        }
    }
}
