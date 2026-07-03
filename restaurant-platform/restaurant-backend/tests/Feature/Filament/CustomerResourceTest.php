<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\Customers\CustomerResource: display,
 * order-count/total-spent/last-order stats, block/unblock with a reason,
 * that admin accounts never leak into this list, that no create/edit/
 * delete route exists at all, and that neither a password nor any Sanctum
 * token ever appears anywhere on this resource.
 */
class CustomerResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(UserRole $role = UserRole::SuperAdmin): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_every_admin_role_can_view_the_customers_list(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager, UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $this->actingAs($this->admin($role))->get(CustomerResource::getUrl('index'))->assertOk();
        }
    }

    public function test_a_customer_cannot_view_the_customers_list(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(CustomerResource::getUrl('index'))->assertForbidden();
    }

    public function test_admin_accounts_never_appear_in_the_customers_list(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin(UserRole::Manager);

        Livewire::actingAs($admin)
            ->test(ListCustomers::class)
            ->assertCanNotSeeTableRecords([$admin, $otherAdmin]);
    }

    public function test_there_is_no_create_or_edit_route(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();

        $this->actingAs($admin)->get('/admin/customers/create')->assertNotFound();
        $this->actingAs($admin)->get("/admin/customers/{$customer->id}/edit")->assertNotFound();
    }

    public function test_view_page_shows_order_count_total_spent_and_last_order(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();
        Order::factory()->create(['user_id' => $customer->id, 'status' => OrderStatus::Delivered, 'total_amount' => 4000]);
        Order::factory()->create(['user_id' => $customer->id, 'status' => OrderStatus::Delivered, 'total_amount' => 1000]);
        Order::factory()->create(['user_id' => $customer->id, 'status' => OrderStatus::Cancelled, 'total_amount' => 9999]);

        $response = $this->actingAs($admin)->get(CustomerResource::getUrl('view', ['record' => $customer]));

        $response->assertOk();
        // 4000 + 1000 = 5000 minor units = "5.000" at JOD's 3 decimals; the
        // cancelled order's 9999 must never be counted.
        $response->assertDontSee('9.999');
    }

    public function test_the_password_and_no_token_ever_appear_on_the_resource(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['password' => bcrypt('SuperSecret123')]);
        $token = $customer->createToken('device')->plainTextToken;

        $indexResponse = $this->actingAs($admin)->get(CustomerResource::getUrl('index'));
        $viewResponse = $this->actingAs($admin)->get(CustomerResource::getUrl('view', ['record' => $customer]));

        $indexResponse->assertDontSee($customer->password, escape: false);
        $viewResponse->assertDontSee($customer->password, escape: false);
        $viewResponse->assertDontSee($token, escape: false);
    }

    public function test_manager_can_block_a_customer_with_a_reason(): void
    {
        $manager = $this->admin(UserRole::Manager);
        $customer = User::factory()->create();
        $customer->createToken('device');

        Livewire::actingAs($manager)
            ->test(ViewCustomer::class, ['record' => $customer->getKey()])
            ->callAction('block', data: ['reason' => 'Repeated no-shows'])
            ->assertHasNoActionErrors();

        $customer->refresh();
        $this->assertFalse($customer->is_active);
        $this->assertSame('Repeated no-shows', $customer->blocked_reason);
        $this->assertSame(0, $customer->tokens()->count());
    }

    public function test_blocking_requires_a_reason(): void
    {
        $manager = $this->admin(UserRole::Manager);
        $customer = User::factory()->create();

        Livewire::actingAs($manager)
            ->test(ViewCustomer::class, ['record' => $customer->getKey()])
            ->callAction('block', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        $this->assertTrue($customer->fresh()->is_active);
    }

    public function test_manager_can_unblock_a_customer(): void
    {
        $manager = $this->admin(UserRole::Manager);
        $customer = User::factory()->create(['is_active' => false, 'blocked_reason' => 'Old reason']);

        Livewire::actingAs($manager)
            ->test(ViewCustomer::class, ['record' => $customer->getKey()])
            ->callAction('unblock')
            ->assertHasNoActionErrors();

        $customer->refresh();
        $this->assertTrue($customer->is_active);
        $this->assertNull($customer->blocked_reason);
    }

    public function test_kitchen_cashier_and_support_cannot_block_a_customer(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $customer = User::factory()->create();

            Livewire::actingAs($this->admin($role))
                ->test(ViewCustomer::class, ['record' => $customer->getKey()])
                ->assertActionHidden('block');
        }
    }

    public function test_block_action_is_not_offered_for_an_already_blocked_customer(): void
    {
        $manager = $this->admin(UserRole::Manager);
        $customer = User::factory()->create(['is_active' => false]);

        Livewire::actingAs($manager)
            ->test(ViewCustomer::class, ['record' => $customer->getKey()])
            ->assertActionHidden('block')
            ->assertActionVisible('unblock');
    }
}
