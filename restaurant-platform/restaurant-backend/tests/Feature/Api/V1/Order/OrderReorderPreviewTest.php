<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Order;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReorderPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantSetting::factory()->create(['currency_code' => 'JOD', 'min_order_amount' => 0]);
    }

    private function makeAvailableProduct(array $attributes = []): Product
    {
        $category = Category::factory()->create(['is_active' => true]);

        return Product::factory()->create(['category_id' => $category->id, 'is_available' => true, ...$attributes]);
    }

    public function test_recomputes_a_past_order_without_creating_a_new_order(): void
    {
        $user = User::factory()->create();
        $product = $this->makeAvailableProduct(['price_amount' => 500]);
        $order = Order::factory()->for($user)->pickup()->withStatus(OrderStatus::Delivered)->create();
        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price_amount' => 500,
            'quantity' => 2,
            'unit_total_amount' => 500,
            'line_total_amount' => 1000,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/reorder-preview", []);

        $response->assertOk()
            ->assertJsonPath('data.source_order.id', $order->id)
            ->assertJsonPath('data.preview.items.0.product_id', $product->id)
            ->assertJsonPath('data.preview.items.0.quantity', 2)
            ->assertJsonPath('data.preview.grand_total.amount_minor', 1000)
            ->assertJsonPath('data.unavailable_items', []);

        $this->assertSame(1, Order::query()->count());
    }

    public function test_reflects_current_price_not_the_original_snapshot(): void
    {
        $user = User::factory()->create();
        $product = $this->makeAvailableProduct(['price_amount' => 500]);
        $order = Order::factory()->for($user)->pickup()->create();
        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price_amount' => 300,
            'quantity' => 1,
            'unit_total_amount' => 300,
            'line_total_amount' => 300,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/reorder-preview", []);

        $response->assertOk()->assertJsonPath('data.preview.items.0.unit_base_price.amount_minor', 500);
    }

    public function test_flags_items_whose_product_was_deleted_as_unavailable(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->pickup()->create();
        OrderItem::factory()->for($order)->create([
            'product_id' => null,
            'product_name' => 'Discontinued Item',
            'quantity' => 1,
        ]);
        $product = $this->makeAvailableProduct(['price_amount' => 400]);
        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price_amount' => 400,
            'quantity' => 1,
            'unit_total_amount' => 400,
            'line_total_amount' => 400,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/reorder-preview", []);

        $response->assertOk()
            ->assertJsonPath('data.unavailable_items.0', 'Discontinued Item')
            ->assertJsonCount(1, 'data.preview.items');
    }

    public function test_applies_an_optional_fresh_coupon(): void
    {
        $user = User::factory()->create();
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);
        $order = Order::factory()->for($user)->pickup()->create();
        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price_amount' => 1000,
            'quantity' => 1,
            'unit_total_amount' => 1000,
            'line_total_amount' => 1000,
        ]);
        Coupon::factory()->create(['code' => 'REORDER10', 'type' => CouponType::Percentage, 'value' => 10, 'min_order_amount' => null]);

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/reorder-preview", [
            'coupon_code' => 'reorder10',
        ]);

        $response->assertOk()->assertJsonPath('data.preview.discount_amount.amount_minor', 100);
    }

    public function test_fails_when_an_item_is_now_unavailable(): void
    {
        $user = User::factory()->create();
        $product = $this->makeAvailableProduct(['price_amount' => 500, 'is_available' => false]);
        $order = Order::factory()->for($user)->pickup()->create();
        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/reorder-preview", []);

        $response->assertStatus(422)->assertJsonPath('errors.code', 'product_unavailable');
    }

    public function test_other_user_cannot_reorder_preview_someone_elses_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = Order::factory()->for($owner)->create();

        $this->actingAs($intruder)->postJson("/api/v1/orders/{$order->id}/reorder-preview", [])->assertForbidden();
    }

    public function test_requires_authentication(): void
    {
        $order = Order::factory()->create();

        $this->postJson("/api/v1/orders/{$order->id}/reorder-preview", [])->assertUnauthorized();
    }
}
