<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers App\Http\Middleware\EnsureAccountIsActive directly: defense in
 * depth for a blocked account whose token somehow still exists (e.g. set
 * `is_active = false` by a path other than
 * App\Services\CustomerBlockingService, which normally revokes tokens at
 * the same time) — every authenticated customer-facing route must still
 * refuse it, not just the login endpoint.
 */
class EnsureAccountIsActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_blocked_customers_token_is_rejected_on_a_protected_route(): void
    {
        $customer = User::factory()->create(['is_active' => false]);
        $token = $customer->createToken('device')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/profile')->assertForbidden();
    }

    public function test_an_active_customers_token_still_works(): void
    {
        $customer = User::factory()->create(['is_active' => true]);
        $token = $customer->createToken('device')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/profile')->assertOk();
    }

    public function test_a_blocked_customer_can_still_log_out_their_own_stale_token(): void
    {
        $customer = User::factory()->create(['is_active' => false]);
        $token = $customer->createToken('device')->plainTextToken;

        // logout/logout-all-devices deliberately skip 'ensure.active' (see
        // routes/api_v1.php) — harmless since a blocked account's tokens
        // are already revoked in practice, but proves that route isn't
        // itself blocked by this middleware.
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    }
}
