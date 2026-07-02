<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_review_a_delivered_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Delivered)->create();

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/review", [
            'rating' => 5,
            'comment' => 'Excellent food!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'Excellent food!');

        $this->assertDatabaseHas('order_reviews', ['order_id' => $order->id, 'user_id' => $user->id, 'rating' => 5]);
    }

    public function test_comment_is_optional(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Delivered)->create();

        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 4])
            ->assertCreated()
            ->assertJsonPath('data.comment', null);
    }

    public function test_cannot_review_an_order_that_is_not_yet_delivered(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Pending)->create();

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 5]);

        $response->assertStatus(422)->assertJsonPath('errors.code', 'order_not_delivered');
        $this->assertDatabaseMissing('order_reviews', ['order_id' => $order->id]);
    }

    public function test_cannot_review_the_same_order_twice(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Delivered)->create();
        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 5])->assertCreated();

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 1]);

        $response->assertStatus(422)->assertJsonPath('errors.code', 'already_reviewed');
        $this->assertSame(1, $order->fresh()->review()->count());
    }

    public function test_rating_must_be_between_1_and_5(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Delivered)->create();

        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);

        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_rating_is_required(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Delivered)->create();

        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/review", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_a_user_cannot_review_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = Order::factory()->for($owner)->withStatus(OrderStatus::Delivered)->create();

        $this->actingAs($intruder)->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 5])
            ->assertForbidden();

        $this->assertDatabaseMissing('order_reviews', ['order_id' => $order->id]);
    }

    public function test_requires_authentication(): void
    {
        $order = Order::factory()->withStatus(OrderStatus::Delivered)->create();

        $this->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 5])->assertUnauthorized();
    }

    public function test_a_submitted_review_is_embedded_in_the_order_detail(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Delivered)->create();
        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 4, 'comment' => 'Good'])->assertCreated();

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.review.rating', 4)
            ->assertJsonPath('data.review.comment', 'Good');
    }
}
