<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\CustomerAddress;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins down the schema's core invariant: past orders never change, even if
 * the product/option they reference is edited or deleted later.
 * See docs/DATABASE_SCHEMA.md "Snapshotting".
 */
class OrderItemSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_products_price_does_not_change_an_existing_order_item(): void
    {
        $product = Product::factory()->create(['price_amount' => 1000]);
        $orderItem = OrderItem::factory()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price_amount' => 1000,
            'unit_total_amount' => 1000,
            'line_total_amount' => 1000,
        ]);

        $product->update(['price_amount' => 5000, 'name' => 'Renamed Product']);

        $orderItem->refresh();
        $this->assertSame(1000, $orderItem->product_price_amount);
        $this->assertNotSame('Renamed Product', $orderItem->product_name);
    }

    public function test_deleting_a_product_leaves_the_order_item_snapshot_intact(): void
    {
        $product = Product::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price_amount' => $product->price_amount,
        ]);

        $product->forceDelete();

        $orderItem->refresh();
        $this->assertNull($orderItem->product_id);
        $this->assertNotNull($orderItem->product_name);
        $this->assertNotNull($orderItem->product_price_amount);
    }

    public function test_deleting_an_option_value_leaves_the_order_item_option_snapshot_intact(): void
    {
        $optionValue = OptionValue::factory()->create(['name' => 'Large', 'price_delta_amount' => 400]);
        $orderItemOption = OrderItemOption::factory()->create([
            'option_value_id' => $optionValue->id,
            'option_group_name' => 'Size',
            'option_value_name' => 'Large',
            'price_delta_amount' => 400,
        ]);

        $optionValue->forceDelete();

        $orderItemOption->refresh();
        $this->assertNull($orderItemOption->option_value_id);
        $this->assertSame('Large', $orderItemOption->option_value_name);
        $this->assertSame(400, $orderItemOption->price_delta_amount);
    }

    public function test_deleting_a_customer_address_leaves_the_orders_delivery_snapshot_intact(): void
    {
        $address = CustomerAddress::factory()->create([
            'address_line1' => '123 Main St',
            'city' => 'Amman',
        ]);
        $order = Order::factory()->create([
            'customer_address_id' => $address->id,
            'delivery_address_line' => '123 Main St',
            'delivery_city' => 'Amman',
        ]);

        $address->delete();

        $order->refresh();
        $this->assertNull($order->customer_address_id);
        $this->assertSame('123 Main St', $order->delivery_address_line);
        $this->assertSame('Amman', $order->delivery_city);
    }
}
