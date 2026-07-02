<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_coupon_cannot_be_recorded_as_used_twice_on_the_same_order(): void
    {
        $coupon = Coupon::factory()->create();
        $order = Order::factory()->create();

        CouponUsage::factory()->create(['coupon_id' => $coupon->id, 'order_id' => $order->id]);

        $this->expectException(QueryException::class);

        CouponUsage::factory()->create(['coupon_id' => $coupon->id, 'order_id' => $order->id]);
    }

    public function test_the_same_coupon_can_be_used_on_different_orders(): void
    {
        $coupon = Coupon::factory()->create();
        $user = User::factory()->create();

        $usage1 = CouponUsage::factory()->create(['coupon_id' => $coupon->id, 'user_id' => $user->id]);
        $usage2 = CouponUsage::factory()->create(['coupon_id' => $coupon->id, 'user_id' => $user->id]);

        $this->assertCount(2, $coupon->usages);
        $this->assertNotSame($usage1->order_id, $usage2->order_id);
    }

    public function test_relationships_resolve(): void
    {
        $usage = CouponUsage::factory()->create();

        $this->assertInstanceOf(Coupon::class, $usage->coupon);
        $this->assertInstanceOf(User::class, $usage->user);
        $this->assertInstanceOf(Order::class, $usage->order);
    }
}
