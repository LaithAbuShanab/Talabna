<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_their_order_with_items_and_timeline(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        OrderItem::factory()->for($order)->create(['product_name' => 'Test Burger']);

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.items.0.product_name', 'Test Burger')
            ->assertJsonPath('data.status_histories', []);
    }

    public function test_other_user_cannot_view_the_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = Order::factory()->for($owner)->create();

        $this->actingAs($intruder)->getJson("/api/v1/orders/{$order->id}")->assertForbidden();
    }

    public function test_returns_404_for_a_nonexistent_order(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/orders/999999')->assertNotFound();
    }

    public function test_requires_authentication(): void
    {
        $order = Order::factory()->create();

        $this->getJson("/api/v1/orders/{$order->id}")->assertUnauthorized();
    }

    public function test_timeline_returns_status_history_oldest_first(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Preparing)->create();
        $order->statusHistories()->create(['from_status' => null, 'status' => OrderStatus::Pending, 'created_at' => now()->subMinutes(10)]);
        $order->statusHistories()->create(['from_status' => OrderStatus::Pending, 'status' => OrderStatus::Accepted, 'created_at' => now()->subMinutes(5)]);
        $order->statusHistories()->create(['from_status' => OrderStatus::Accepted, 'status' => OrderStatus::Preparing, 'created_at' => now()]);

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/timeline");

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.1.status', 'accepted')
            ->assertJsonPath('data.2.status', 'preparing');
    }

    public function test_other_user_cannot_view_the_timeline(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = Order::factory()->for($owner)->create();

        $this->actingAs($intruder)->getJson("/api/v1/orders/{$order->id}/timeline")->assertForbidden();
    }
}
