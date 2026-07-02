<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /**
     * Demo coupons covering both types and an expired one, so the checkout
     * flow (once built) has real cases to validate against from day one.
     */
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'type' => CouponType::Percentage,
                'value' => 10,
                'max_discount_amount' => 500,
                'min_order_amount' => 500,
                'usage_limit' => 500,
                'per_user_limit' => 1,
                'starts_at' => null,
                'expires_at' => now()->addMonths(6),
                'is_active' => true,
            ],
            [
                'code' => 'SAVE5',
                'type' => CouponType::FixedAmount,
                'value' => 500,
                'max_discount_amount' => null,
                'min_order_amount' => 2000,
                'usage_limit' => null,
                'per_user_limit' => 3,
                'starts_at' => null,
                'expires_at' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'code' => 'EXPIRED20',
                'type' => CouponType::Percentage,
                'value' => 20,
                'max_discount_amount' => 1000,
                'min_order_amount' => null,
                'usage_limit' => null,
                'per_user_limit' => null,
                'starts_at' => null,
                'expires_at' => now()->subMonth(),
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::query()->updateOrCreate(
                ['code' => $coupon['code']],
                collect($coupon)->except('code')->all(),
            );
        }
    }
}
