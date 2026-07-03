<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\CustomerAddresses\CustomerAddressResource;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\CustomerAddresses\CustomerAddressResource —
 * deliberately read-mostly: every admin role can list/view, but no
 * create/edit/delete route exists at all (see App\Policies\
 * CustomerAddressPolicy, extended for this resource but otherwise
 * unchanged for the customer-facing API).
 */
class CustomerAddressResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(UserRole $role = UserRole::SuperAdmin): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_every_admin_role_can_view_the_list_and_a_single_address(): void
    {
        $address = CustomerAddress::factory()->create();

        foreach ([UserRole::SuperAdmin, UserRole::Manager, UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = $this->admin($role);
            $this->actingAs($admin)->get(CustomerAddressResource::getUrl('index'))->assertOk();
            $this->actingAs($admin)->get(CustomerAddressResource::getUrl('view', ['record' => $address]))->assertOk();
        }
    }

    public function test_a_customer_cannot_view_the_admin_address_list(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(CustomerAddressResource::getUrl('index'))->assertForbidden();
    }

    public function test_there_is_no_create_or_edit_route(): void
    {
        $admin = $this->admin();
        $address = CustomerAddress::factory()->create();

        $this->actingAs($admin)->get('/admin/customer-addresses/create')->assertNotFound();
        $this->actingAs($admin)->get("/admin/customer-addresses/{$address->id}/edit")->assertNotFound();
    }

    public function test_view_page_shows_the_address_details(): void
    {
        $admin = $this->admin();
        $address = CustomerAddress::factory()->create(['city' => 'Amman', 'address_line1' => '123 Rainbow Street']);

        $response = $this->actingAs($admin)->get(CustomerAddressResource::getUrl('view', ['record' => $address]));

        $response->assertOk()->assertSee('Amman')->assertSee('123 Rainbow Street');
    }
}
