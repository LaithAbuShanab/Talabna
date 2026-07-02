<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Menu;

use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\RestaurantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves App\Observers\MenuCacheObserver actually invalidates
 * App\Services\MenuCacheService's cached reads whenever the underlying
 * models change — the same mechanism a future Filament Resource would
 * trigger, since it hooks Eloquent model events rather than anything
 * Filament-specific.
 */
class MenuCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantSetting::factory()->create(['currency_code' => 'JOD']);
    }

    public function test_editing_a_product_invalidates_the_products_cache(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price_amount' => 450]);

        $this->getJson('/api/v1/products')->assertJsonPath('data.data.0.price.amount_minor', 450);

        $product->update(['price_amount' => 999]);

        $this->getJson('/api/v1/products')->assertJsonPath('data.data.0.price.amount_minor', 999);
    }

    public function test_editing_a_category_invalidates_both_categories_and_products_cache(): void
    {
        $category = Category::factory()->create(['name' => 'Burgers']);
        Product::factory()->for($category)->create();

        $this->getJson('/api/v1/categories')->assertJsonPath('data.0.name.en', 'Burgers');
        $this->getJson('/api/v1/products')->assertJsonCount(1, 'data.data');

        $category->update(['is_active' => false]);

        $this->getJson('/api/v1/categories')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/products')->assertJsonCount(0, 'data.data');
    }

    public function test_editing_an_option_value_invalidates_the_products_cache(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();
        $group = OptionGroup::factory()->create();
        $value = OptionValue::factory()->for($group, 'optionGroup')->create(['name' => 'Small']);
        $product->optionGroups()->attach($group->id, ['is_required' => false, 'sort_order' => 0]);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertJsonPath('data.option_groups.0.values.0.name.en', 'Small');

        $value->update(['name' => 'Tiny']);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertJsonPath('data.option_groups.0.values.0.name.en', 'Tiny');
    }

    public function test_editing_a_delivery_zone_invalidates_its_cache(): void
    {
        $zone = DeliveryZone::factory()->create(['name' => 'Zone A']);

        $this->getJson('/api/v1/delivery-zones')->assertJsonPath('data.0.name', 'Zone A');

        $zone->update(['name' => 'Zone Renamed']);

        $this->getJson('/api/v1/delivery-zones')->assertJsonPath('data.0.name', 'Zone Renamed');
    }

    public function test_editing_restaurant_settings_invalidates_its_cache(): void
    {
        $this->getJson('/api/v1/restaurant')->assertJsonPath('data.name', RestaurantSetting::current()->restaurant_name);

        RestaurantSetting::current()->update(['restaurant_name' => 'New Name']);

        $this->getJson('/api/v1/restaurant')->assertJsonPath('data.name', 'New Name');
    }

    public function test_editing_business_hours_invalidates_its_cache(): void
    {
        $hour = BusinessHour::factory()->create(['day_of_week' => 1, 'is_closed' => false]);

        $this->getJson('/api/v1/restaurant/hours')->assertJsonPath('data.0.is_closed', false);

        $hour->update(['is_closed' => true]);

        $this->getJson('/api/v1/restaurant/hours')->assertJsonPath('data.0.is_closed', true);
    }
}
