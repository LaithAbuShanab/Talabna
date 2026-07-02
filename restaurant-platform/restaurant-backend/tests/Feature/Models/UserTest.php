<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\UserRole;
use App\Models\CustomerAddress;
use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_defaults_to_customer_and_casts_to_enum(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Customer, $user->role);
    }

    public function test_admin_factory_state_sets_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertSame(UserRole::Admin, $admin->role);
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $user = User::create([
            'name' => 'Sneaky Customer',
            'email' => 'sneaky@example.com',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);

        $this->assertSame(UserRole::Customer, $user->fresh()->role);
    }

    public function test_only_admin_role_can_access_the_filament_panel(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $panel = Filament::getPanel('admin');

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertFalse($customer->canAccessPanel($panel));
    }

    public function test_user_has_many_addresses(): void
    {
        $user = User::factory()->create();
        CustomerAddress::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->addresses);
        $this->assertInstanceOf(CustomerAddress::class, $user->addresses->first());
    }

    public function test_user_has_many_orders(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->orders);
    }

    public function test_user_has_many_device_tokens(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->assertCount(1, $user->deviceTokens);
    }
}
