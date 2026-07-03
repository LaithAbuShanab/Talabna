<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\UserRole;
use App\Models\AdminActivityLog;
use App\Models\User;
use App\Services\CustomerBlockingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers App\Services\CustomerBlockingService directly — the Filament
 * action-level behavior (visibility, permissions) is covered by
 * tests/Feature/Filament/CustomerResourceTest.php; this is the service's
 * own contract.
 */
class CustomerBlockingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CustomerBlockingService
    {
        return app(CustomerBlockingService::class);
    }

    public function test_block_sets_is_active_false_and_records_the_reason(): void
    {
        $customer = User::factory()->create(['is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Manager]);

        $blocked = $this->service()->block($customer, 'Chargeback fraud', $admin);

        $this->assertFalse($blocked->is_active);
        $this->assertSame('Chargeback fraud', $blocked->blocked_reason);
    }

    public function test_block_revokes_every_existing_token(): void
    {
        $customer = User::factory()->create();
        $customer->createToken('phone');
        $customer->createToken('tablet');
        $this->assertSame(2, $customer->tokens()->count());

        $this->service()->block($customer, 'Fraud', null);

        $this->assertSame(0, $customer->tokens()->count());
    }

    public function test_block_logs_an_admin_activity_entry(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Manager]);

        $this->service()->block($customer, 'Abuse', $admin);

        $this->assertSame(1, AdminActivityLog::query()->where('action', 'customer.blocked')->count());
        $log = AdminActivityLog::query()->where('action', 'customer.blocked')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($customer->id, $log->subject_id);
    }

    public function test_unblock_restores_is_active_and_clears_the_reason(): void
    {
        $customer = User::factory()->create(['is_active' => false, 'blocked_reason' => 'Old reason']);
        $admin = User::factory()->create(['role' => UserRole::Manager]);

        $unblocked = $this->service()->unblock($customer, $admin);

        $this->assertTrue($unblocked->is_active);
        $this->assertNull($unblocked->blocked_reason);
    }

    public function test_unblock_logs_an_admin_activity_entry(): void
    {
        $customer = User::factory()->create(['is_active' => false]);
        $admin = User::factory()->create(['role' => UserRole::Manager]);

        $this->service()->unblock($customer, $admin);

        $this->assertSame(1, AdminActivityLog::query()->where('action', 'customer.unblocked')->count());
    }
}
