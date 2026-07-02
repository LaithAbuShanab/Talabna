<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\Products\RelationManagers\
 * ImagesRelationManager: uploading a product's primary/additional images and
 * the "exactly one primary image" invariant enforced by normalizePrimary().
 */
class ProductImagesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_creating_the_first_image_makes_it_primary_even_if_not_marked(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->image('one.jpg'),
                'is_primary' => false,
            ])
            ->assertHasNoTableActionErrors();

        $image = ProductImage::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertTrue($image->is_primary);
    }

    public function test_marking_a_second_image_primary_unmarks_the_first(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $first = ProductImage::factory()->primary()->create(['product_id' => $product->id, 'sort_order' => 0]);

        Livewire::actingAs($this->manager())
            ->test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->image('two.jpg'),
                'is_primary' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertSame(
            1,
            ProductImage::query()->where('product_id', $product->id)->where('is_primary', true)->count()
        );
    }

    public function test_the_set_primary_action_swaps_the_primary_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $primary = ProductImage::factory()->primary()->create(['product_id' => $product->id]);
        $other = ProductImage::factory()->create(['product_id' => $product->id]);

        Livewire::actingAs($this->manager())
            ->test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('setPrimary', record: $other)
            ->assertHasNoTableActionErrors();

        $this->assertFalse($primary->fresh()->is_primary);
        $this->assertTrue($other->fresh()->is_primary);
    }

    public function test_an_image_is_required_to_create_a_record(): void
    {
        $product = Product::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: ['path' => null])
            ->assertHasTableActionErrors(['path' => 'required']);
    }
}
