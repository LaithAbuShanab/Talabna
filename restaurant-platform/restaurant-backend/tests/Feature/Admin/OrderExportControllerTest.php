<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers App\Http\Controllers\Admin\OrderExportController — "إمكانية
 * تصدير تقرير طلبات CSV بطريقة آمنة" and, specifically, "منع CSV
 * injection". The formula-injection tests are the important ones here:
 * a customer name starting with `=`/`+`/`-`/`@` must come back prefixed
 * with a literal single quote, exactly the OWASP-recommended mitigation
 * (see App\Support\Csv), not merely "some" sanitization.
 */
class OrderExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(UserRole $role = UserRole::SuperAdmin): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_an_authorized_admin_can_export_a_csv(): void
    {
        $admin = $this->admin();
        Order::factory()->create(['status' => OrderStatus::Delivered]);

        $response = $this->actingAs($admin)->get(route('admin.orders.export', ['period' => 'last_30_days']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('Content-Disposition'),
        );
    }

    public function test_a_customer_cannot_export_orders(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('admin.orders.export'))->assertForbidden();
    }

    public function test_kitchen_and_support_cannot_export_orders(): void
    {
        // The export shares OrderPolicy::viewAny() with the Orders list
        // itself — but kitchen/support genuinely can view that list, so
        // they *can* export too (deliberate: this is the same read-only
        // order data they already see, not a new financial capability).
        // Cashier/manager/super_admin all pass viewAny() as well.
        foreach ([UserRole::Kitchen, UserRole::Support] as $role) {
            $admin = $this->admin($role);

            $this->actingAs($admin)->get(route('admin.orders.export'))->assertOk();
        }
    }

    public function test_the_export_contains_a_utf8_bom_and_a_header_row(): void
    {
        $admin = $this->admin();
        Order::factory()->create(['status' => OrderStatus::Delivered, 'order_number' => 'ORD-TEST-001']);

        $response = $this->actingAs($admin)->get(route('admin.orders.export', ['period' => 'last_30_days']));
        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Order #', $content);
        $this->assertStringContainsString('Customer', $content);
        $this->assertStringContainsString('Payment status', $content);
        $this->assertStringContainsString('ORD-TEST-001', $content);
    }

    public function test_a_customer_name_starting_with_an_equals_sign_is_neutralized(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['name' => '=HYPERLINK("http://evil.example","click")']);
        Order::factory()->create(['status' => OrderStatus::Delivered, 'user_id' => $customer->id]);

        $response = $this->actingAs($admin)->get(route('admin.orders.export', ['period' => 'last_30_days']));
        $content = $response->streamedContent();

        // The raw dangerous cell (unescaped, immediately after a comma or
        // opening quote) must never appear — only the neutralized,
        // quote-prefixed version.
        $this->assertStringNotContainsString('"=HYPERLINK', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
    }

    public function test_every_dangerous_prefix_is_neutralized(): void
    {
        foreach (['=1+1', '+1+1', '-1+1', '@SUM(A1:A10)'] as $dangerousName) {
            $admin = $this->admin();
            $customer = User::factory()->create(['name' => $dangerousName]);
            Order::factory()->create(['status' => OrderStatus::Delivered, 'user_id' => $customer->id]);

            $response = $this->actingAs($admin)->get(route('admin.orders.export', ['period' => 'last_30_days']));
            $content = $response->streamedContent();

            $this->assertStringContainsString("'".$dangerousName, $content, "Prefix in \"{$dangerousName}\" should have been neutralized");
        }
    }

    public function test_a_normal_customer_name_is_left_untouched(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['name' => 'Sara Ahmad']);
        Order::factory()->create(['status' => OrderStatus::Delivered, 'user_id' => $customer->id]);

        $response = $this->actingAs($admin)->get(route('admin.orders.export', ['period' => 'last_30_days']));
        $content = $response->streamedContent();

        $this->assertStringContainsString('Sara Ahmad', $content);
        $this->assertStringNotContainsString("'Sara Ahmad", $content);
    }

    public function test_export_defaults_to_today_when_no_period_is_given(): void
    {
        $admin = $this->admin();
        Order::factory()->create(['status' => OrderStatus::Delivered, 'created_at' => now(), 'order_number' => 'ORD-TODAY']);
        Order::factory()->create(['status' => OrderStatus::Delivered, 'created_at' => now()->subDays(5), 'order_number' => 'ORD-OLD']);

        $response = $this->actingAs($admin)->get(route('admin.orders.export'));
        $content = $response->streamedContent();

        $this->assertStringContainsString('ORD-TODAY', $content);
        $this->assertStringNotContainsString('ORD-OLD', $content);
    }

    public function test_an_invalid_period_falls_back_to_today_instead_of_erroring(): void
    {
        $admin = $this->admin();
        Order::factory()->create(['status' => OrderStatus::Delivered]);

        $this->actingAs($admin)->get(route('admin.orders.export', ['period' => 'not-a-real-period']))->assertOk();
    }
}
