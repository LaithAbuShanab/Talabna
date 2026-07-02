<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_number_is_generated_automatically_in_the_expected_format(): void
    {
        $order = Order::factory()->create();

        $this->assertMatchesRegularExpression('/^ORD-\d{4}-\d{6}$/', $order->order_number);
        $this->assertStringContainsString((string) now()->year, $order->order_number);
    }

    public function test_order_numbers_increment_sequentially_within_a_year(): void
    {
        $first = Order::factory()->create();
        $second = Order::factory()->create();

        $this->assertNotSame($first->order_number, $second->order_number);
        $this->assertSame(
            ((int) substr($first->order_number, -6)) + 1,
            (int) substr($second->order_number, -6),
        );
    }

    public function test_order_number_is_never_overwritten_if_already_set(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-2026-999999']);

        $this->assertSame('ORD-2026-999999', $order->order_number);
    }

    public function test_order_number_column_enforces_uniqueness_at_the_database_level(): void
    {
        Order::factory()->create(['order_number' => 'ORD-2026-000042']);

        $this->expectException(QueryException::class);

        Order::factory()->create(['order_number' => 'ORD-2026-000042']);
    }

    public function test_money_and_enum_fields_cast_correctly(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Accepted,
            'delivery_type' => DeliveryType::Pickup,
            'payment_method' => PaymentMethod::CardOnDelivery,
            'payment_status' => PaymentStatus::Paid,
            'subtotal_amount' => '1500',
            'total_amount' => '1500',
        ]);

        $fresh = $order->fresh();

        $this->assertSame(OrderStatus::Accepted, $fresh->status);
        $this->assertSame(DeliveryType::Pickup, $fresh->delivery_type);
        $this->assertSame(PaymentMethod::CardOnDelivery, $fresh->payment_method);
        $this->assertSame(PaymentStatus::Paid, $fresh->payment_status);
        $this->assertIsInt($fresh->subtotal_amount);
        $this->assertSame(1500, $fresh->subtotal_amount);
    }

    public function test_order_relationships(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create();
        $zone = DeliveryZone::factory()->create();
        $address = CustomerAddress::factory()->create(['user_id' => $user->id]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'delivery_zone_id' => $zone->id,
            'customer_address_id' => $address->id,
        ]);

        OrderItem::factory()->create(['order_id' => $order->id]);
        OrderStatusHistory::factory()->create(['order_id' => $order->id]);
        Payment::factory()->create(['order_id' => $order->id]);

        $this->assertTrue($order->user->is($user));
        $this->assertTrue($order->coupon->is($coupon));
        $this->assertTrue($order->deliveryZone->is($zone));
        $this->assertTrue($order->customerAddress->is($address));
        $this->assertCount(1, $order->items);
        $this->assertCount(1, $order->statusHistories);
        $this->assertCount(1, $order->payments);
    }

    public function test_deleting_a_user_with_orders_is_restricted(): void
    {
        $user = User::factory()->create();
        Order::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);

        DB::table('users')->where('id', $user->id)->delete();
    }

    public function test_deleting_a_coupon_nulls_out_the_orders_coupon_id_and_preserves_the_order(): void
    {
        $coupon = Coupon::factory()->create();
        $order = Order::factory()->create(['coupon_id' => $coupon->id]);

        $coupon->forceDelete();

        $this->assertNull($order->fresh()->coupon_id);
        $this->assertNotNull($order->fresh());
    }
}
