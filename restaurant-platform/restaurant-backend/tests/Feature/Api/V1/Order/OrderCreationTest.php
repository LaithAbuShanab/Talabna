<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Order;

use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openRestaurantNow();
    }

    private function openRestaurantNow(): void
    {
        RestaurantSetting::factory()->create(['is_accepting_orders' => true, 'min_order_amount' => 0]);

        BusinessHour::factory()->create([
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
            'is_closed' => false,
        ]);
    }

    private function makeAvailableProduct(array $attributes = []): Product
    {
        $category = Category::factory()->create(['is_active' => true]);

        return Product::factory()->create(['category_id' => $category->id, 'is_available' => true, ...$attributes]);
    }

    public function test_authenticated_user_can_create_a_pickup_order(): void
    {
        $user = User::factory()->create();
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'pickup',
            'payment_method' => 'cash_on_delivery',
        ], ['Idempotency-Key' => 'key-1']);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_amount', 1000)
            ->assertJsonPath('data.can_be_cancelled', true);

        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'idempotency_key' => 'key-1']);
    }

    public function test_requires_an_idempotency_key_header(): void
    {
        $user = User::factory()->create();
        $product = $this->makeAvailableProduct();

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'pickup',
            'payment_method' => 'cash_on_delivery',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_repeating_the_same_idempotency_key_returns_the_original_order_unchanged(): void
    {
        $user = User::factory()->create();
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);

        $first = $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'pickup',
            'payment_method' => 'cash_on_delivery',
        ], ['Idempotency-Key' => 'same-key']);

        $second = $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'delivery_type' => 'pickup',
            'payment_method' => 'cash_on_delivery',
        ], ['Idempotency-Key' => 'same-key']);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1000, $second->json('data.total_amount'));
        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());
    }

    public function test_a_different_idempotency_key_creates_a_separate_order(): void
    {
        $user = User::factory()->create();
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);

        $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'pickup',
            'payment_method' => 'cash_on_delivery',
        ], ['Idempotency-Key' => 'key-a'])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'pickup',
            'payment_method' => 'cash_on_delivery',
        ], ['Idempotency-Key' => 'key-b'])->assertCreated();

        $this->assertSame(2, Order::query()->where('user_id', $user->id)->count());
    }

    public function test_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(
            '/api/v1/orders',
            [],
            ['Idempotency-Key' => 'key-1']
        );

        $response->assertUnprocessable()->assertJsonValidationErrors(['items', 'delivery_type', 'payment_method']);
    }

    public function test_rejects_an_order_when_the_restaurant_is_closed(): void
    {
        RestaurantSetting::current()->update(['is_accepting_orders' => false]);
        $user = User::factory()->create();
        $product = $this->makeAvailableProduct();

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'pickup',
            'payment_method' => 'cash_on_delivery',
        ], ['Idempotency-Key' => 'key-1']);

        $response->assertStatus(422)->assertJsonPath('errors.code', 'restaurant_closed');
    }

    public function test_rejects_an_unavailable_product(): void
    {
        $user = User::factory()->create();
        $product = $this->makeAvailableProduct(['is_available' => false]);

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'pickup',
            'payment_method' => 'cash_on_delivery',
        ], ['Idempotency-Key' => 'key-1']);

        $response->assertStatus(422)->assertJsonPath('errors.code', 'product_unavailable');
    }

    public function test_requires_authentication(): void
    {
        $product = $this->makeAvailableProduct();

        $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_type' => 'pickup',
            'payment_method' => 'cash_on_delivery',
        ], ['Idempotency-Key' => 'key-1'])->assertUnauthorized();
    }
}
