<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_cancel_a_pending_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Pending)->create();

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/cancel", [
            'reason' => 'Changed my mind',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'cancellation_reason' => 'Changed my mind']);
    }

    public function test_customer_can_cancel_an_accepted_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Accepted)->create();

        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'x'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_customer_cannot_cancel_an_order_that_is_already_preparing(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Preparing)->create();

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'x']);

        $response->assertStatus(403)->assertJsonPath('errors.code', 'unauthorized_transition');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'preparing']);
    }

    public function test_customer_cannot_cancel_a_delivered_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Delivered)->create();

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'x']);

        $response->assertStatus(422)->assertJsonPath('errors.code', 'terminal_state');
    }

    public function test_cancellation_requires_a_reason(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Pending)->create();

        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/cancel", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_a_user_cannot_cancel_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = Order::factory()->for($owner)->withStatus(OrderStatus::Pending)->create();

        $this->actingAs($intruder)->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'x'])
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_cancelling_records_a_status_history_entry(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Pending)->create();

        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'No longer needed'])->assertOk();

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => 'cancelled',
            'note' => 'No longer needed',
        ]);
    }

    public function test_requires_authentication(): void
    {
        $order = Order::factory()->withStatus(OrderStatus::Pending)->create();

        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'x'])->assertUnauthorized();
    }
}
