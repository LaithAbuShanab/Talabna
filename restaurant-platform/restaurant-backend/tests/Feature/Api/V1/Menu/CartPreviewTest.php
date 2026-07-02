<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Menu;

use App\Enums\CouponType;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\RestaurantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantSetting::factory()->create([
            'currency_code' => 'JOD',
            'min_order_amount' => 0,
        ]);
    }

    public function test_previews_a_simple_pickup_cart(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price_amount' => 450]);

        $response = $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'delivery_type' => 'pickup',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.line_total.amount_minor', 900)
            ->assertJsonPath('data.grand_total.amount_minor', 900)
            ->assertJsonPath('data.delivery.type', 'pickup')
            ->assertJsonPath('data.coupon', null);
    }

    public function test_previews_a_delivery_cart_with_zone_fee(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price_amount' => 1000]);
        $zone = DeliveryZone::factory()->create(['delivery_fee_amount' => 300, 'min_order_amount' => null]);

        $response = $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'delivery',
            'delivery_zone_id' => $zone->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.delivery.zone_id', $zone->id)
            ->assertJsonPath('data.delivery.fee_amount.amount_minor', 300)
            ->assertJsonPath('data.grand_total.amount_minor', 1300);
    }

    public function test_prices_selected_options(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price_amount' => 400]);
        $group = OptionGroup::factory()->create();
        $value = OptionValue::factory()->for($group, 'optionGroup')->create(['price_delta_amount' => 150]);
        $product->optionGroups()->attach($group->id, ['is_required' => false, 'sort_order' => 0]);

        $response = $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'option_value_ids' => [$value->id]]],
            'delivery_type' => 'pickup',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.items.0.unit_options_total.amount_minor', 150)
            ->assertJsonPath('data.items.0.line_total.amount_minor', 550);
    }

    public function test_applies_a_valid_coupon(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price_amount' => 1000]);
        $coupon = Coupon::factory()->create([
            'code' => 'SAVE10',
            'type' => CouponType::Percentage,
            'value' => 10,
            'min_order_amount' => null,
        ]);

        $response = $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'pickup',
            'coupon_code' => 'save10',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.coupon.id', $coupon->id)
            ->assertJsonPath('data.coupon.code', 'SAVE10')
            ->assertJsonPath('data.discount_amount.amount_minor', 100)
            ->assertJsonPath('data.grand_total.amount_minor', 900);
    }

    public function test_rejects_an_invalid_coupon_code(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price_amount' => 1000]);

        $response = $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'pickup',
            'coupon_code' => 'DOES-NOT-EXIST',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code', 'coupon_invalid');
    }

    public function test_rejects_an_expired_coupon(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['price_amount' => 1000]);
        Coupon::factory()->expired()->create(['code' => 'OLD20']);

        $response = $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'pickup',
            'coupon_code' => 'OLD20',
        ]);

        $response->assertStatus(422)->assertJsonPath('errors.code', 'coupon_expired');
    }

    public function test_rejects_an_unavailable_product(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->unavailable()->create();

        $response = $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'pickup',
        ]);

        $response->assertStatus(422)->assertJsonPath('errors.code', 'product_unavailable');
    }

    public function test_rejects_a_nonexistent_product(): void
    {
        $response = $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => 999999, 'quantity' => 1]],
            'delivery_type' => 'pickup',
        ]);

        $response->assertStatus(422)->assertJsonPath('errors.code', 'product_not_found');
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/cart/preview', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['items', 'delivery_type']);
    }

    public function test_validates_delivery_type_is_a_known_enum_value(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();

        $response = $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'teleport',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['delivery_type']);
    }
}
