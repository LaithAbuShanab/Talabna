<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * The single place a customer account is ever blocked/unblocked — see
 * docs/ADMIN_CUSTOMERS.md. Blocking is not cosmetic: it revokes every
 * existing Sanctum token immediately (so an already-logged-in session
 * can't keep placing orders) and is also enforced going forward by
 * App\Http\Middleware\EnsureAccountIsActive (every authenticated request)
 * and App\Http\Controllers\Api\V1\AuthController::login() (no new token
 * issued to a blocked account). `is_active`/`blocked_reason` are both
 * excluded from User's #[Fillable(...)] — this service is the only
 * intended writer, via forceFill(), same privilege-escalation guard
 * already used for `role`.
 */
final class CustomerBlockingService
{
    public function __construct(private readonly AdminActivityLogger $activityLogger) {}

    public function block(User $customer, string $reason, ?User $actor = null): User
    {
        $customer->forceFill([
            'is_active' => false,
            'blocked_reason' => $reason,
        ])->save();

        $customer->tokens()->delete();

        $this->activityLogger->log($actor, 'customer.blocked', $customer, $reason);

        return $customer->refresh();
    }

    public function unblock(User $customer, ?User $actor = null): User
    {
        $customer->forceFill([
            'is_active' => true,
            'blocked_reason' => null,
        ])->save();

        $this->activityLogger->log($actor, 'customer.unblocked', $customer);

        return $customer->refresh();
    }
}
