<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_only_their_own_addresses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        CustomerAddress::factory()->for($user)->create();
        CustomerAddress::factory()->for($other)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/addresses');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_user_can_create_an_address(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/addresses', [
            'address_line1' => '123 Main St',
            'city' => 'Amman',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $user->id,
            'address_line1' => '123 Main St',
        ]);
    }

    public function test_first_address_is_automatically_made_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/addresses', [
            'address_line1' => '123 Main St',
            'city' => 'Amman',
        ]);

        $response->assertJsonPath('data.is_default', true);
    }

    public function test_creating_a_new_default_address_unsets_the_previous_default(): void
    {
        $user = User::factory()->create();
        $first = CustomerAddress::factory()->for($user)->default()->create();

        $this->actingAs($user)->postJson('/api/v1/addresses', [
            'address_line1' => '456 Second St',
            'city' => 'Amman',
            'is_default' => true,
        ])->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
    }

    public function test_user_can_update_their_own_address(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->create();

        $this->actingAs($user)->putJson("/api/v1/addresses/{$address->id}", [
            'label' => 'Home',
        ])->assertOk()->assertJsonPath('data.label', 'Home');
    }

    public function test_user_cannot_update_another_users_address(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $address = CustomerAddress::factory()->for($owner)->create();

        $this->actingAs($intruder)->putJson("/api/v1/addresses/{$address->id}", [
            'label' => 'Hacked',
        ])->assertForbidden();

        $this->assertDatabaseMissing('customer_addresses', ['id' => $address->id, 'label' => 'Hacked']);
    }

    public function test_user_cannot_delete_another_users_address(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $address = CustomerAddress::factory()->for($owner)->create();

        $this->actingAs($intruder)->deleteJson("/api/v1/addresses/{$address->id}")->assertForbidden();

        $this->assertDatabaseHas('customer_addresses', ['id' => $address->id]);
    }

    public function test_user_can_delete_their_own_address(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->create();

        $this->actingAs($user)->deleteJson("/api/v1/addresses/{$address->id}")->assertOk();

        $this->assertDatabaseMissing('customer_addresses', ['id' => $address->id]);
    }

    public function test_user_can_set_an_address_as_default(): void
    {
        $user = User::factory()->create();
        $first = CustomerAddress::factory()->for($user)->default()->create();
        $second = CustomerAddress::factory()->for($user)->create();

        $this->actingAs($user)->postJson("/api/v1/addresses/{$second->id}/default")
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_user_cannot_set_another_users_address_as_default(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $address = CustomerAddress::factory()->for($owner)->create();

        $this->actingAs($intruder)->postJson("/api/v1/addresses/{$address->id}/default")->assertForbidden();
    }

    /**
     * The requirement is: deleting an address linked to a past order must
     * never corrupt that order's data. Order rows store a snapshot of the
     * address (delivery_address_line/city/lat/long) independent of the
     * customer_addresses row, and customer_address_id is nullOnDelete — so
     * deleting the address should leave the order's snapshot fully intact.
     */
    public function test_deleting_an_address_linked_to_an_old_order_preserves_the_orders_snapshot(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->create([
            'address_line1' => '123 Main St',
            'city' => 'Amman',
        ]);

        $order = Order::factory()->for($user)->create([
            'customer_address_id' => $address->id,
            'delivery_address_line' => '123 Main St',
            'delivery_city' => 'Amman',
        ]);

        $this->actingAs($user)->deleteJson("/api/v1/addresses/{$address->id}")->assertOk();

        $order->refresh();
        $this->assertNull($order->customer_address_id);
        $this->assertSame('123 Main St', $order->delivery_address_line);
        $this->assertSame('Amman', $order->delivery_city);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
}
