<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CustomerAddress;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class OrderSeeder extends Seeder
{
    /**
     * One demo order per OrderStatus (plus a couple of extras), each with
     * real order items/options snapshotted from the seeded catalog and a
     * plausible status_history chain — see statusChain() below. Depends on
     * CustomerSeeder and ProductSeeder having already run.
     *
     * Idempotent by construction: if any order already belongs to a demo
     * customer, the whole seeder is skipped rather than growing the order
     * count on every re-run.
     */
    public function run(): void
    {
        $customers = User::query()->whereIn('email', [
            'customer1@example.com',
            'customer2@example.com',
            'customer3@example.com',
            'customer4@example.com',
            'customer5@example.com',
        ])->get();

        if ($customers->isEmpty()) {
            return;
        }

        if (Order::query()->whereIn('user_id', $customers->pluck('id'))->exists()) {
            return;
        }

        $products = Product::query()->with('optionGroups.values')->get();
        $zones = DeliveryZone::query()->get();

        if ($products->isEmpty()) {
            return;
        }

        $scenarios = [
            ['status' => OrderStatus::Pending, 'delivery' => true],
            ['status' => OrderStatus::Accepted, 'delivery' => true],
            ['status' => OrderStatus::Preparing, 'delivery' => false],
            ['status' => OrderStatus::Ready, 'delivery' => true],
            ['status' => OrderStatus::OutForDelivery, 'delivery' => true],
            ['status' => OrderStatus::Delivered, 'delivery' => true],
            ['status' => OrderStatus::Delivered, 'delivery' => false],
            ['status' => OrderStatus::Cancelled, 'delivery' => true],
            ['status' => OrderStatus::Rejected, 'delivery' => false],
        ];

        foreach ($scenarios as $index => $scenario) {
            $customer = $customers[$index % $customers->count()];
            $this->createDemoOrder($customer, $products, $zones, $scenario['status'], $scenario['delivery']);
        }
    }

    private function createDemoOrder(User $customer, Collection $products, Collection $zones, OrderStatus $status, bool $isDelivery): void
    {
        $deliveryType = $isDelivery ? DeliveryType::Delivery : DeliveryType::Pickup;
        $address = $isDelivery ? CustomerAddress::query()->where('user_id', $customer->id)->first() : null;
        $zone = $isDelivery ? $zones->first() : null;

        $lineItems = $products->random(min(2, $products->count()));
        $subtotal = 0;
        $itemsData = [];

        foreach ($lineItems as $product) {
            $quantity = fake()->numberBetween(1, 2);
            $optionsTotal = 0;
            $selectedOptions = [];

            foreach ($product->optionGroups as $group) {
                $value = $group->values->firstWhere('is_default', true) ?? $group->values->first();

                if ($value) {
                    $optionsTotal += $value->price_delta_amount;
                    $selectedOptions[] = [
                        'option_value_id' => $value->id,
                        'option_group_name' => $group->name,
                        'option_value_name' => $value->name,
                        'price_delta_amount' => $value->price_delta_amount,
                    ];
                }
            }

            $unitTotal = $product->price_amount + $optionsTotal;
            $lineTotal = $unitTotal * $quantity;
            $subtotal += $lineTotal;

            $itemsData[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_price_amount' => $product->price_amount,
                'quantity' => $quantity,
                'unit_total_amount' => $unitTotal,
                'line_total_amount' => $lineTotal,
                'options' => $selectedOptions,
            ];
        }

        $deliveryFee = $isDelivery ? ($zone->delivery_fee_amount ?? 300) : 0;
        $isTerminalFailure = in_array($status, [OrderStatus::Cancelled, OrderStatus::Rejected], strict: true);
        $paymentStatus = match (true) {
            $status === OrderStatus::Delivered => PaymentStatus::Paid,
            $isTerminalFailure => PaymentStatus::Pending,
            default => PaymentStatus::Pending,
        };

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'status' => $status,
            'delivery_type' => $deliveryType,
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'payment_status' => $paymentStatus,
            'subtotal_amount' => $subtotal,
            'discount_amount' => 0,
            'delivery_fee_amount' => $deliveryFee,
            'total_amount' => $subtotal + $deliveryFee,
            'delivery_zone_id' => $zone?->id,
            'customer_address_id' => $address?->id,
            'delivery_address_line' => $address?->address_line1,
            'delivery_city' => $address?->city,
            'delivery_latitude' => $address?->latitude,
            'delivery_longitude' => $address?->longitude,
            'customer_notes' => fake()->optional()->sentence(),
            'rejection_reason' => $status === OrderStatus::Rejected ? 'Kitchen is over capacity right now.' : null,
            'cancellation_reason' => $status === OrderStatus::Cancelled ? 'Customer requested cancellation.' : null,
            'expected_delivery_at' => $status->isTerminal() ? null : now()->addMinutes(30),
        ]);

        foreach ($itemsData as $itemData) {
            $options = $itemData['options'];
            unset($itemData['options']);

            $orderItem = OrderItem::query()->create(['order_id' => $order->id, ...$itemData]);

            foreach ($options as $optionData) {
                OrderItemOption::query()->create(['order_item_id' => $orderItem->id, ...$optionData]);
            }
        }

        foreach ($this->statusChain($status, $isDelivery) as $step) {
            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'status' => $step,
                'note' => $step === $status ? 'Demo seed data.' : null,
            ]);
        }

        if ($paymentStatus === PaymentStatus::Paid) {
            Payment::query()->create([
                'order_id' => $order->id,
                'method' => $order->payment_method,
                'status' => PaymentStatus::Paid,
                'amount' => $order->total_amount,
                'paid_at' => now(),
            ]);
        }
    }

    /**
     * @return list<OrderStatus>
     */
    private function statusChain(OrderStatus $finalStatus, bool $isDelivery): array
    {
        return match ($finalStatus) {
            OrderStatus::Pending => [OrderStatus::Pending],
            OrderStatus::Accepted => [OrderStatus::Pending, OrderStatus::Accepted],
            OrderStatus::Preparing => [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Preparing],
            OrderStatus::Ready => [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready],
            OrderStatus::OutForDelivery => [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::OutForDelivery],
            OrderStatus::Delivered => $isDelivery
                ? [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::Delivered]
                : [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Delivered],
            OrderStatus::Cancelled => [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Cancelled],
            OrderStatus::Rejected => [OrderStatus::Pending, OrderStatus::Rejected],
        };
    }
}
