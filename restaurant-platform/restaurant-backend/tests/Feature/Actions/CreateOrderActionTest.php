<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\CreateOrderAction;
use App\DataTransferObjects\Cart\CartItemInputData;
use App\DataTransferObjects\Order\CreateOrderData;
use App\Enums\CouponType;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderCreated;
use App\Exceptions\CartPricingException;
use App\Exceptions\OrderCreationException;
use App\Http\Resources\OrderResource;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class CreateOrderActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateOrderAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(CreateOrderAction::class);
        $this->openRestaurantNow();
    }

    private function openRestaurantNow(): void
    {
        RestaurantSetting::factory()->create(['is_accepting_orders' => true, 'min_order_amount' => 0]);

        BusinessHour::factory()->create([
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
            'is_closed' => false,
        ]);
    }

    private function makeAvailableProduct(array $attributes = []): Product
    {
        $category = Category::factory()->create(['is_active' => true]);

        return Product::factory()->create([
            'category_id' => $category->id,
            'is_available' => true,
            ...$attributes,
        ]);
    }

    private function makeData(array $overrides = []): CreateOrderData
    {
        $product = $overrides['product'] ?? $this->makeAvailableProduct(['price_amount' => 1000]);
        $user = $overrides['user'] ?? User::factory()->create();

        return new CreateOrderData(
            userId: $overrides['userId'] ?? $user->id,
            items: $overrides['items'] ?? [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: $overrides['deliveryType'] ?? DeliveryType::Pickup,
            paymentMethod: $overrides['paymentMethod'] ?? PaymentMethod::CashOnDelivery,
            idempotencyKey: $overrides['idempotencyKey'] ?? (string) str()->uuid(),
            deliveryZoneId: $overrides['deliveryZoneId'] ?? null,
            customerAddressId: $overrides['customerAddressId'] ?? null,
            couponCode: $overrides['couponCode'] ?? null,
            customerNotes: $overrides['customerNotes'] ?? null,
        );
    }

    // --- Happy paths: delivery & pickup -------------------------------------------------

    public function test_it_creates_a_pickup_order(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);
        $user = User::factory()->create();

        $order = $this->action->execute($this->makeData([
            'user' => $user,
            'product' => $product,
            'deliveryType' => DeliveryType::Pickup,
        ]));

        $this->assertInstanceOf(Order::class, $order);
        $this->assertMatchesRegularExpression('/^ORD-\d{4}-\d{6}$/', $order->order_number);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(DeliveryType::Pickup, $order->delivery_type);
        $this->assertSame(0, $order->delivery_fee_amount);
        $this->assertNull($order->delivery_address_line);
        $this->assertSame(1000, $order->total_amount);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $user->id]);
    }

    public function test_it_creates_a_delivery_order_with_a_valid_address(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->create([
            'user_id' => $user->id,
            'address_line1' => '42 Rainbow Street',
            'city' => 'Amman',
        ]);
        $zone = DeliveryZone::factory()->create(['delivery_fee_amount' => 350, 'is_active' => true, 'min_order_amount' => null]);

        $order = $this->action->execute($this->makeData([
            'user' => $user,
            'product' => $product,
            'deliveryType' => DeliveryType::Delivery,
            'deliveryZoneId' => $zone->id,
            'customerAddressId' => $address->id,
        ]));

        $this->assertSame(DeliveryType::Delivery, $order->delivery_type);
        $this->assertSame(350, $order->delivery_fee_amount);
        $this->assertSame(1350, $order->total_amount);
        $this->assertSame('42 Rainbow Street', $order->delivery_address_line);
        $this->assertSame('Amman', $order->delivery_city);
        $this->assertSame($address->id, $order->customer_address_id);
        $this->assertSame($zone->id, $order->delivery_zone_id);
    }

    public function test_pickup_does_not_require_an_address(): void
    {
        $order = $this->action->execute($this->makeData(['deliveryType' => DeliveryType::Pickup]));

        $this->assertNull($order->customer_address_id);
    }

    // --- Address validation -------------------------------------------------

    public function test_it_throws_when_delivery_requested_without_an_address(): void
    {
        $zone = DeliveryZone::factory()->create(['is_active' => true]);

        try {
            $this->action->execute($this->makeData([
                'deliveryType' => DeliveryType::Delivery,
                'deliveryZoneId' => $zone->id,
            ]));
            $this->fail('Expected OrderCreationException was not thrown.');
        } catch (OrderCreationException $e) {
            $this->assertSame('delivery_address_required', $e->errorCode);
        }

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_it_throws_when_the_address_belongs_to_a_different_user(): void
    {
        $user = User::factory()->create();
        $otherUsersAddress = CustomerAddress::factory()->create();
        $zone = DeliveryZone::factory()->create(['is_active' => true]);

        try {
            $this->action->execute($this->makeData([
                'user' => $user,
                'deliveryType' => DeliveryType::Delivery,
                'deliveryZoneId' => $zone->id,
                'customerAddressId' => $otherUsersAddress->id,
            ]));
            $this->fail('Expected OrderCreationException was not thrown.');
        } catch (OrderCreationException $e) {
            $this->assertSame('delivery_address_invalid', $e->errorCode);
        }
    }

    // --- Idempotency -------------------------------------------------

    public function test_it_returns_the_same_order_for_a_repeated_idempotency_key(): void
    {
        $user = User::factory()->create();
        $product = $this->makeAvailableProduct();
        $data = $this->makeData(['user' => $user, 'product' => $product, 'idempotencyKey' => 'checkout-abc-123']);

        $first = $this->action->execute($data);
        $second = $this->action->execute($data);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->order_number, $second->order_number);
        $this->assertSame(1, Order::query()->where('idempotency_key', 'checkout-abc-123')->count());
    }

    public function test_different_idempotency_keys_create_different_orders(): void
    {
        $user = User::factory()->create();
        $product = $this->makeAvailableProduct();

        $first = $this->action->execute($this->makeData(['user' => $user, 'product' => $product, 'idempotencyKey' => 'key-one']));
        $second = $this->action->execute($this->makeData(['user' => $user, 'product' => $product, 'idempotencyKey' => 'key-two']));

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Order::query()->where('user_id', $user->id)->count());
    }

    public function test_the_same_idempotency_key_can_be_reused_by_a_different_user(): void
    {
        $product = $this->makeAvailableProduct();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $orderA = $this->action->execute($this->makeData(['user' => $userA, 'product' => $product, 'idempotencyKey' => 'shared-key']));
        $orderB = $this->action->execute($this->makeData(['user' => $userB, 'product' => $product, 'idempotencyKey' => 'shared-key']));

        $this->assertNotSame($orderA->id, $orderB->id);
    }

    // --- Restaurant closed -------------------------------------------------

    public function test_it_throws_when_the_restaurant_is_not_accepting_orders(): void
    {
        RestaurantSetting::current()->update(['is_accepting_orders' => false]);

        try {
            $this->action->execute($this->makeData());
            $this->fail('Expected OrderCreationException was not thrown.');
        } catch (OrderCreationException $e) {
            $this->assertSame('restaurant_closed', $e->errorCode);
        }

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_it_throws_when_outside_todays_business_hours(): void
    {
        BusinessHour::query()->where('day_of_week', now()->dayOfWeek)->update(['is_closed' => true]);

        try {
            $this->action->execute($this->makeData());
            $this->fail('Expected OrderCreationException was not thrown.');
        } catch (OrderCreationException $e) {
            $this->assertSame('restaurant_closed', $e->errorCode);
        }
    }

    // --- Product became unavailable -------------------------------------------------

    public function test_it_throws_when_a_product_became_unavailable(): void
    {
        $product = $this->makeAvailableProduct();
        $product->update(['is_available' => false]);

        try {
            $this->action->execute($this->makeData(['product' => $product]));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('product_unavailable', $e->errorCode);
        }

        $this->assertDatabaseCount('orders', 0);
    }

    // --- Empty cart -------------------------------------------------

    public function test_it_throws_for_an_empty_cart(): void
    {
        try {
            $this->action->execute($this->makeData(['items' => []]));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('cart_empty', $e->errorCode);
        }
    }

    // --- Transaction rollback -------------------------------------------------

    public function test_it_rolls_back_everything_if_something_fails_partway_through(): void
    {
        $productA = $this->makeAvailableProduct(['price_amount' => 500]);
        $productB = $this->makeAvailableProduct(['price_amount' => 700]);

        $data = $this->makeData([
            'items' => [
                new CartItemInputData(productId: $productA->id, quantity: 1),
                new CartItemInputData(productId: $productB->id, quantity: 1),
            ],
        ]);

        $callCount = 0;
        OrderItem::creating(function () use (&$callCount): void {
            $callCount++;

            if ($callCount === 2) {
                throw new RuntimeException('Simulated failure partway through order creation.');
            }
        });

        try {
            try {
                $this->action->execute($data);
                $this->fail('Expected RuntimeException was not thrown.');
            } catch (RuntimeException $e) {
                $this->assertSame('Simulated failure partway through order creation.', $e->getMessage());
            }
        } finally {
            OrderItem::flushEventListeners();
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    // --- Client-supplied prices are impossible / ignored -------------------------------------------------

    public function test_order_amounts_always_come_from_the_database_never_the_caller(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 500]);

        $first = $this->action->execute($this->makeData(['product' => $product, 'idempotencyKey' => 'price-check-1']));
        $this->assertSame(500, $first->subtotal_amount);

        // CreateOrderData/CartItemInputData have no price field at all (see
        // the DTOs) — the only way the total can change is the database
        // price changing, which we do here and re-order to prove it.
        $product->update(['price_amount' => 4242]);

        $second = $this->action->execute($this->makeData(['product' => $product, 'idempotencyKey' => 'price-check-2']));
        $this->assertSame(4242, $second->subtotal_amount);
    }

    // --- Status history & payment -------------------------------------------------

    public function test_it_creates_an_initial_pending_status_history_record(): void
    {
        $order = $this->action->execute($this->makeData());

        $this->assertCount(1, $order->statusHistories);
        $this->assertSame(OrderStatus::Pending, $order->statusHistories->first()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => 'pending',
        ]);
    }

    public function test_it_creates_a_pending_payment_record_for_the_full_total(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 1500]);

        $order = $this->action->execute($this->makeData([
            'product' => $product,
            'paymentMethod' => PaymentMethod::CardOnDelivery,
        ]));

        $this->assertCount(1, $order->payments);
        $payment = $order->payments->first();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(PaymentMethod::CardOnDelivery, $payment->method);
        $this->assertSame($order->total_amount, $payment->amount);
    }

    // --- Snapshotting -------------------------------------------------

    public function test_order_items_snapshot_product_name_and_price(): void
    {
        $product = $this->makeAvailableProduct(['name' => 'Snapshot Burger', 'price_amount' => 650]);

        $order = $this->action->execute($this->makeData(['product' => $product]));

        $item = $order->items->first();
        $this->assertSame('Snapshot Burger', $item->product_name);
        $this->assertSame(650, $item->product_price_amount);

        $product->update(['name' => 'Renamed Burger', 'price_amount' => 9999]);

        $item->refresh();
        $this->assertSame('Snapshot Burger', $item->product_name);
        $this->assertSame(650, $item->product_price_amount);
    }

    // --- Coupons -------------------------------------------------

    public function test_applying_a_coupon_discounts_the_order_and_records_a_coupon_usage(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create([
            'code' => 'SAVE100',
            'type' => CouponType::FixedAmount,
            'value' => 100,
            'min_order_amount' => null,
        ]);

        $order = $this->action->execute($this->makeData([
            'user' => $user,
            'product' => $product,
            'couponCode' => 'SAVE100',
        ]));

        $this->assertSame(100, $order->discount_amount);
        $this->assertSame(900, $order->total_amount);
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'discount_amount' => 100,
        ]);
    }

    // --- Events -------------------------------------------------

    public function test_it_dispatches_order_created_after_a_successful_checkout(): void
    {
        Event::fake([OrderCreated::class]);

        $order = $this->action->execute($this->makeData());

        Event::assertDispatched(OrderCreated::class, fn (OrderCreated $event): bool => $event->order->is($order));
    }

    public function test_it_does_not_dispatch_order_created_when_checkout_fails(): void
    {
        Event::fake([OrderCreated::class]);

        RestaurantSetting::current()->update(['is_accepting_orders' => false]);

        try {
            $this->action->execute($this->makeData());
        } catch (OrderCreationException) {
            // expected
        }

        Event::assertNotDispatched(OrderCreated::class);
    }

    // --- Resource -------------------------------------------------

    public function test_order_resource_returns_the_expected_shape(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);

        $order = $this->action->execute($this->makeData(['product' => $product]));

        $array = (new OrderResource($order))->response()->getData(true);

        $this->assertSame($order->order_number, $array['data']['order_number']);
        $this->assertSame('pending', $array['data']['status']);
        $this->assertSame(1000, $array['data']['total_amount']);
        $this->assertCount(1, $array['data']['items']);
        $this->assertCount(1, $array['data']['status_histories']);
        $this->assertCount(1, $array['data']['payments']);
    }
}
