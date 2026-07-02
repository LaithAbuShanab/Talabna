<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\ProductImages\Pages\CreateProductImage;
use App\Filament\Resources\ProductImages\Pages\EditProductImage;
use App\Filament\Resources\ProductImages\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the standalone App\Filament\Resources\ProductImages\
 * ProductImageResource (a flat, cross-product view) — the per-product
 * primary/additional-image workflow is covered separately by
 * ProductImagesRelationManagerTest.
 */
class ProductImageResourceTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_a_manager_can_create_a_product_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $file = UploadedFile::fake()->image('dish.jpg');

        Livewire::actingAs($this->manager())
            ->test(CreateProductImage::class)
            ->fillForm([
                'product_id' => $product->id,
                'path' => $file,
                'sort_order' => 2,
                'is_primary' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $image = ProductImage::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(2, $image->sort_order);
        $this->assertTrue($image->is_primary);
    }

    public function test_product_and_image_are_required(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateProductImage::class)
            ->fillForm(['product_id' => null, 'path' => null])
            ->call('create')
            ->assertHasFormErrors(['product_id' => 'required', 'path' => 'required']);
    }

    public function test_a_manager_can_edit_a_product_image(): void
    {
        Storage::fake('public');
        $path = 'products/existing.jpg';
        Storage::disk('public')->put($path, 'fake-image-contents');
        $image = ProductImage::factory()->create(['path' => $path, 'sort_order' => 1]);

        Livewire::actingAs($this->manager())
            ->test(EditProductImage::class, ['record' => $image->getKey()])
            ->fillForm(['sort_order' => 9])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(9, $image->fresh()->sort_order);
    }

    public function test_a_manager_can_delete_a_product_image(): void
    {
        $image = ProductImage::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditProductImage::class, ['record' => $image->getKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    public function test_super_admin_and_manager_can_view_the_product_images_list(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get(ProductImageResource::getUrl('index'))->assertOk();
        }
    }

    public function test_kitchen_cashier_and_support_cannot_view_the_product_images_list(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = User::factory()->create(['role' => $role]);

            $this->actingAs($admin)->get(ProductImageResource::getUrl('index'))->assertForbidden();
        }
    }
}
