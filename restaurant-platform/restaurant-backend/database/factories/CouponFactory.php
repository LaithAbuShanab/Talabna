<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE##??')),
            'type' => CouponType::Percentage,
            'value' => fake()->numberBetween(5, 30),
            'max_discount_amount' => fake()->optional()->numberBetween(500, 2000),
            'min_order_amount' => fake()->optional()->numberBetween(500, 3000),
            'usage_limit' => fake()->optional()->numberBetween(10, 500),
            'per_user_limit' => 1,
            'starts_at' => null,
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }

    public function fixedAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CouponType::FixedAmount,
            'value' => fake()->numberBetween(100, 1000),
            'max_discount_amount' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
