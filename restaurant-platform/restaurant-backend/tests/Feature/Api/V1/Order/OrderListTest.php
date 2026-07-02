<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderListTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_the_authenticated_users_own_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Order::factory()->for($user)->create();
        Order::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/orders');

        $response->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_orders_are_returned_newest_first(): void
    {
        $user = User::factory()->create();
        $oldest = Order::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
        $newest = Order::factory()->for($user)->create(['created_at' => now()]);
        $middle = Order::factory()->for($user)->create(['created_at' => now()->subDay()]);

        $response = $this->actingAs($user)->getJson('/api/v1/orders');

        $response->assertJsonPath('data.data.0.id', $newest->id)
            ->assertJsonPath('data.data.1.id', $middle->id)
            ->assertJsonPath('data.data.2.id', $oldest->id);
    }

    public function test_filters_by_status(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->withStatus(OrderStatus::Pending)->create();
        Order::factory()->for($user)->withStatus(OrderStatus::Delivered)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/orders?status=delivered');

        $response->assertOk()->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.status', 'delivered');
    }

    public function test_rejects_an_unknown_status_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/orders?status=not-a-real-status')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_paginates_results(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->count(25)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/orders?per_page=10&page=2');

        $response->assertOk()
            ->assertJsonCount(10, 'data.data')
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.total', 25)
            ->assertJsonPath('data.meta.last_page', 3);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }

    public function test_list_does_not_eagerly_load_items_to_keep_the_payload_lean(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/orders');

        $response->assertOk()->assertJsonMissingPath('data.data.0.items');
    }
}
