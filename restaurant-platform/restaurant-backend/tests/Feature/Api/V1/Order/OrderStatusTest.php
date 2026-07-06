<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Covers GET /api/v1/orders/{order}/status — see docs/ORDER_STATUS_POLLING.md
 * for the full polling strategy this endpoint exists for.
 */
class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_fetch_the_current_status_and_timeline(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Preparing)->create();
        $order->statusHistories()->create(['from_status' => OrderStatus::Pending, 'status' => OrderStatus::Accepted, 'created_at' => now()->subMinutes(5)]);
        $order->statusHistories()->create(['from_status' => OrderStatus::Accepted, 'status' => OrderStatus::Preparing, 'created_at' => now()]);

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/status");

        $response->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.status', 'preparing')
            ->assertJsonPath('data.can_be_cancelled', false)
            ->assertJsonCount(2, 'data.timeline')
            ->assertJsonPath('data.timeline.0.status', 'accepted')
            ->assertJsonPath('data.timeline.1.status', 'preparing');
    }

    public function test_the_response_never_includes_items_payments_or_addresses(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/status");

        $data = $response->json('data');
        $this->assertArrayNotHasKey('items', $data);
        $this->assertArrayNotHasKey('payments', $data);
        $this->assertArrayNotHasKey('delivery_address', $data);
        $this->assertArrayNotHasKey('subtotal_amount', $data);
    }

    public function test_updated_at_is_returned_as_the_version_marker(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/status");

        $response->assertJsonPath('data.updated_at', $order->fresh()->updated_at->toIso8601String());
    }

    public function test_other_user_cannot_view_the_status(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = Order::factory()->for($owner)->create();

        $this->actingAs($intruder)->getJson("/api/v1/orders/{$order->id}/status")->assertForbidden();
    }

    public function test_requires_authentication(): void
    {
        $order = Order::factory()->create();

        $this->getJson("/api/v1/orders/{$order->id}/status")->assertUnauthorized();
    }

    public function test_returns_404_for_a_nonexistent_order(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/orders/999999/status')->assertNotFound();
    }

    // --- updated_since ---------------------------------------------------

    public function test_updated_since_in_the_future_returns_an_unchanged_response_without_the_timeline(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Preparing)->create();
        $order->statusHistories()->create(['status' => OrderStatus::Preparing]);

        $future = urlencode($order->updated_at->addMinute()->toIso8601String());

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/status?updated_since={$future}");

        $response->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.status', 'preparing')
            ->assertJsonMissingPath('data.timeline');
    }

    public function test_updated_since_before_the_last_change_returns_the_full_changed_payload(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Preparing)->create();
        $order->statusHistories()->create(['status' => OrderStatus::Preparing]);

        $past = urlencode($order->updated_at->subMinute()->toIso8601String());

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/status?updated_since={$past}");

        $response->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonCount(1, 'data.timeline');
    }

    public function test_no_updated_since_always_returns_the_full_changed_payload(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/status");

        $response->assertOk()->assertJsonPath('data.changed', true);
    }

    public function test_updated_since_must_be_a_valid_date(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/status?updated_since=not-a-date");

        $response->assertUnprocessable()->assertJsonValidationErrors(['updated_since']);
    }

    // --- HTTP conditional GET (ETag / Last-Modified) ----------------------

    public function test_the_response_carries_etag_and_last_modified_headers(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/status");

        $response->assertHeader('ETag')->assertHeader('Last-Modified');
    }

    public function test_a_matching_if_none_match_returns_304_with_an_empty_body(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $etag = $this->actingAs($user)
            ->getJson("/api/v1/orders/{$order->id}/status")
            ->headers->get('ETag');

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/status", ['If-None-Match' => $etag]);

        $response->assertStatus(304);
        $this->assertSame('', $response->getContent());
    }

    public function test_a_stale_if_none_match_after_a_status_change_returns_200_again(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->withStatus(OrderStatus::Pending)->create();

        $etag = $this->actingAs($user)
            ->getJson("/api/v1/orders/{$order->id}/status")
            ->headers->get('ETag');

        // `updated_at` (and so the ETag derived from it) only has
        // whole-second precision — see docs/ORDER_STATUS_POLLING.md's
        // "known limitation" note — so the test advances the clock to
        // guarantee this update lands in a different second, exactly like
        // any two real status changes would in practice (orders move
        // between statuses minutes apart, never twice in the same second).
        Carbon::setTestNow(now()->addSecond());
        $order->forceFill(['status' => OrderStatus::Accepted])->save();

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/status", ['If-None-Match' => $etag]);

        $response->assertOk()->assertJsonPath('data.status', 'accepted');

        Carbon::setTestNow();
    }

    // --- Rate limiting -----------------------------------------------------

    public function test_it_is_rate_limited_after_thirty_requests_per_minute(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/status")->assertOk();
        }

        $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/status")->assertStatus(429);
    }
}
