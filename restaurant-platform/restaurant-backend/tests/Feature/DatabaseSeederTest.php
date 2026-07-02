<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\DeliveryZone;
use App\Models\OptionGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\RestaurantSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_base_seeder_runs_cleanly_and_creates_the_expected_baseline_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, RestaurantSetting::query()->count());
        $this->assertSame(7, BusinessHour::query()->count());
        $this->assertGreaterThanOrEqual(4, Category::query()->count());
        $this->assertGreaterThanOrEqual(15, Product::query()->count());
        $this->assertGreaterThanOrEqual(4, OptionGroup::query()->count());
        $this->assertGreaterThanOrEqual(2, DeliveryZone::query()->count());
        $this->assertTrue(User::query()->where('role', UserRole::Admin)->exists());
    }

    public function test_every_product_has_a_local_placeholder_image(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(Product::query()->count(), ProductImage::query()->count());

        ProductImage::query()->get()->each(function (ProductImage $image): void {
            $this->assertStringStartsWith('images/placeholders/', $image->path);
            $this->assertFileExists(public_path($image->path));
        });
    }

    public function test_delivery_zones_have_a_fee_and_a_minimum_order_amount(): void
    {
        $this->seed(DatabaseSeeder::class);

        DeliveryZone::query()->get()->each(function (DeliveryZone $zone): void {
            $this->assertGreaterThan(0, $zone->delivery_fee_amount);
            $this->assertNotNull($zone->min_order_amount);
        });
    }

    public function test_demo_coupons_cover_both_types_and_an_expired_case(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Coupon::query()->where('code', 'WELCOME10')->exists());
        $this->assertTrue(Coupon::query()->where('code', 'SAVE5')->exists());
        $this->assertTrue(Coupon::query()->where('expires_at', '<', now())->exists());
    }

    public function test_admin_account_is_not_a_production_password_and_can_access_the_panel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_demo_customers_have_addresses(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customers = User::query()->where('role', UserRole::Customer)->get();

        $this->assertGreaterThanOrEqual(5, $customers->count());

        $customers->each(function (User $customer): void {
            $this->assertGreaterThan(0, CustomerAddress::query()->where('user_id', $customer->id)->count());
        });
    }

    public function test_demo_orders_cover_every_order_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $seededStatuses = Order::query()->get()->pluck('status')->unique();

        foreach (OrderStatus::cases() as $status) {
            $this->assertTrue($seededStatuses->contains($status), "No demo order has status {$status->value}");
        }
    }

    public function test_demo_orders_have_items_and_a_status_history(): void
    {
        $this->seed(DatabaseSeeder::class);

        Order::query()->with(['items', 'statusHistories'])->get()->each(function (Order $order): void {
            $this->assertGreaterThan(0, $order->items->count());
            $this->assertGreaterThan(0, $order->statusHistories->count());
            $this->assertSame($order->status, $order->statusHistories->last()->status);
        });
    }

    public function test_running_the_seeder_twice_does_not_duplicate_any_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $counts = [
            RestaurantSetting::query()->count(),
            BusinessHour::query()->count(),
            Category::query()->count(),
            Product::query()->count(),
            OptionGroup::query()->count(),
            DeliveryZone::query()->count(),
            Coupon::query()->count(),
            User::query()->count(),
            CustomerAddress::query()->count(),
            Order::query()->count(),
        ];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, [
            RestaurantSetting::query()->count(),
            BusinessHour::query()->count(),
            Category::query()->count(),
            Product::query()->count(),
            OptionGroup::query()->count(),
            DeliveryZone::query()->count(),
            Coupon::query()->count(),
            User::query()->count(),
            CustomerAddress::query()->count(),
            Order::query()->count(),
        ]);
    }
}
