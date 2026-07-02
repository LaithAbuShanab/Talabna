<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Menu;

use App\Enums\OptionSelectionType;
use App\Models\Category;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\RestaurantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantSetting::factory()->create(['currency_code' => 'JOD']);
    }

    public function test_lists_only_available_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create(['name' => 'Available']);
        Product::factory()->for($category)->unavailable()->create(['name' => 'Unavailable']);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.name.en', 'Available');
    }

    public function test_lists_only_active_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create(['name' => 'Active']);
        Product::factory()->for($category)->inactive()->create(['name' => 'Inactive']);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.name.en', 'Active');
    }

    public function test_excludes_products_belonging_to_an_inactive_category(): void
    {
        $activeCategory = Category::factory()->create();
        $inactiveCategory = Category::factory()->inactive()->create();
        Product::factory()->for($activeCategory)->create(['name' => 'Visible']);
        Product::factory()->for($inactiveCategory)->create(['name' => 'Hidden']);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.name.en', 'Visible');
    }

    public function test_filters_by_category_id(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();
        Product::factory()->for($categoryA)->create(['name' => 'In A']);
        Product::factory()->for($categoryB)->create(['name' => 'In B']);

        $response = $this->getJson("/api/v1/products?category_id={$categoryA->id}");

        $response->assertOk()->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.name.en', 'In A');
    }

    public function test_searches_by_english_name(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create(['name' => 'Margherita Pizza']);
        Product::factory()->for($category)->create(['name' => 'Beef Burger']);

        $response = $this->getJson('/api/v1/products?search=pizza');

        $response->assertOk()->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.name.en', 'Margherita Pizza');
    }

    public function test_searches_by_arabic_name(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create(['name' => 'Margherita Pizza', 'name_ar' => 'بيتزا مارغريتا']);
        Product::factory()->for($category)->create(['name' => 'Beef Burger', 'name_ar' => 'برجر لحم']);

        $response = $this->getJson('/api/v1/products?search='.urlencode('بيتزا'));

        $response->assertOk()->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.name.en', 'Margherita Pizza');
    }

    public function test_paginates_results(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->count(25)->create();

        $response = $this->getJson('/api/v1/products?per_page=10&page=2');

        $response->assertOk()
            ->assertJsonCount(10, 'data.data')
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.total', 25)
            ->assertJsonPath('data.meta.last_page', 3);
    }

    public function test_show_returns_full_detail_with_nested_option_groups_and_values(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();
        ProductImage::factory()->for($product)->primary()->create();

        $group = OptionGroup::factory()->create(['selection_type' => OptionSelectionType::Single]);
        $value = OptionValue::factory()->for($group, 'optionGroup')->create();
        $product->optionGroups()->attach($group->id, ['is_required' => true, 'sort_order' => 0]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonCount(1, 'data.images')
            ->assertJsonCount(1, 'data.option_groups')
            ->assertJsonPath('data.option_groups.0.id', $group->id)
            ->assertJsonPath('data.option_groups.0.is_required', true)
            ->assertJsonPath('data.option_groups.0.values.0.id', $value->id);
    }

    public function test_show_excludes_an_inactive_option_value_from_the_nested_group(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();

        $group = OptionGroup::factory()->create();
        $activeValue = OptionValue::factory()->for($group, 'optionGroup')->create(['is_active' => true]);
        OptionValue::factory()->for($group, 'optionGroup')->create(['is_active' => false]);
        $product->optionGroups()->attach($group->id, ['is_required' => false, 'sort_order' => 0]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()->assertJsonCount(1, 'data.option_groups.0.values');
        $response->assertJsonPath('data.option_groups.0.values.0.id', $activeValue->id);
    }

    public function test_show_returns_404_for_an_unavailable_product(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->unavailable()->create();

        $this->getJson("/api/v1/products/{$product->id}")->assertNotFound();
    }

    public function test_show_returns_404_for_a_nonexistent_product(): void
    {
        $this->getJson('/api/v1/products/999999')->assertNotFound();
    }

    public function test_price_is_formatted_with_amount_minor_currency_and_formatted_string(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create(['price_amount' => 450]);

        $response = $this->getJson('/api/v1/products');

        $response->assertJsonPath('data.data.0.price.amount_minor', 450)
            ->assertJsonPath('data.data.0.price.formatted', '0.450')
            ->assertJsonPath('data.data.0.price.currency', 'JOD');
    }
}
