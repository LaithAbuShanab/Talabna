<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(500, 10000);
        $deliveryFee = fake()->numberBetween(0, 1000);
        $discount = 0;

        return [
            // order_number is intentionally omitted: Order::booted() generates
            // it automatically on creation (see App\Models\Order).
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
            'delivery_type' => DeliveryType::Delivery,
            'payment_method' => PaymentMethod::CashOnDelivery,
            'payment_status' => PaymentStatus::Pending,
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discount,
            'delivery_fee_amount' => $deliveryFee,
            'total_amount' => $subtotal - $discount + $deliveryFee,
            'customer_notes' => fake()->optional()->sentence(),
            'expected_delivery_at' => now()->addMinutes(fake()->numberBetween(20, 60)),
        ];
    }

    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_type' => DeliveryType::Pickup,
            'delivery_fee_amount' => 0,
            'total_amount' => $attributes['subtotal_amount'] - $attributes['discount_amount'],
        ]);
    }

    public function withStatus(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
