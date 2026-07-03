<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers App\Http\Controllers\Admin\OrderPrintController — the
 * thermal-printer-friendly receipt reached from the order detail page's
 * "Print" action. No external printing service is integrated; this just
 * proves the route renders the right data and is properly authorized.
 */
class OrderPrintControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_view_the_print_page_with_full_order_detail(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
        $customer = User::factory()->create(['name' => 'Print Test Customer', 'phone' => '+962790000111']);
        $order = Order::factory()->create(['user_id' => $customer->id]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Test Burger']);
        OrderItemOption::factory()->create([
            'order_item_id' => $item->id,
            'option_group_name' => 'Size',
            'option_value_name' => 'Large',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders.print', ['order' => $order]));

        $response->assertOk();
        $response->assertSee($order->order_number);
        $response->assertSee('Print Test Customer');
        $response->assertSee('+962790000111');
        $response->assertSee('Test Burger');
        $response->assertSee('Size: Large');
    }

    public function test_the_print_page_requires_authentication(): void
    {
        $order = Order::factory()->create();

        // No web "login" route exists in this backend (see bootstrap/app.php's
        // redirectGuestsTo(fn () => null)) — a guest gets a plain 401 here,
        // same as everywhere else, not a redirect to a login page.
        $this->get(route('admin.orders.print', ['order' => $order]))->assertUnauthorized();
    }

    public function test_a_customer_can_view_the_print_page_for_their_own_order(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($customer)->get(route('admin.orders.print', ['order' => $order]))->assertOk();
    }

    public function test_a_customer_cannot_view_the_print_page_for_someone_elses_order(): void
    {
        $stranger = User::factory()->create();
        $order = Order::factory()->create();

        $this->actingAs($stranger)->get(route('admin.orders.print', ['order' => $order]))->assertForbidden();
    }
}
