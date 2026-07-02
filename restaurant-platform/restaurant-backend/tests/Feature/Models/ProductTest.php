<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\OptionSelectionType;
use App\Models\Category;
use App\Models\OptionGroup;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_belongs_to_a_category(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($product->category->is($category));
    }

    public function test_product_soft_deletes_instead_of_hard_deleting(): void
    {
        $product = Product::factory()->create();

        $product->delete();

        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_deleting_a_category_with_products_is_restricted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->expectException(QueryException::class);

        DB::table('categories')->where('id', $category->id)->delete();
    }

    public function test_option_groups_pivot_carries_is_required_and_sort_order(): void
    {
        $product = Product::factory()->create();
        $group = OptionGroup::factory()->create(['selection_type' => OptionSelectionType::Single]);

        $product->optionGroups()->attach($group->id, ['is_required' => true, 'sort_order' => 2]);

        $pivot = $product->optionGroups()->first()->pivot;

        $this->assertTrue((bool) $pivot->is_required);
        $this->assertSame(2, $pivot->sort_order);
    }

    public function test_the_same_option_group_can_be_required_for_one_product_and_optional_for_another(): void
    {
        $group = OptionGroup::factory()->create();
        $pizza = Product::factory()->create();
        $drink = Product::factory()->create();

        $pizza->optionGroups()->attach($group->id, ['is_required' => true]);
        $drink->optionGroups()->attach($group->id, ['is_required' => false]);

        $this->assertTrue((bool) $pizza->optionGroups()->first()->pivot->is_required);
        $this->assertFalse((bool) $drink->optionGroups()->first()->pivot->is_required);
    }
}
