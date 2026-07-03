<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerAddress;
use App\Models\User;

/**
 * `viewAny()`/`view()`'s admin-role branch guards the new, deliberately
 * read-mostly App\Filament\Resources\CustomerAddresses\
 * CustomerAddressResource (docs/ADMIN_CUSTOMERS.md) — every other ability
 * stays ownership-only exactly as it already was for the customer-facing
 * API (App\Http\Controllers\Api\V1\CustomerAddressController), which is
 * precisely what makes the admin resource "read-mostly" without any extra
 * code: an admin can never satisfy `update()`/`delete()`'s ownership check,
 * so Filament's edit/delete actions are refused for every admin regardless
 * of role — no separate "read-only for admins" rule needed.
 */
class CustomerAddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isAdmin();
    }

    public function view(User $user, CustomerAddress $address): bool
    {
        return $user->role->isAdmin() || $user->id === $address->user_id;
    }

    public function update(User $user, CustomerAddress $address): bool
    {
        return $user->id === $address->user_id;
    }

    public function delete(User $user, CustomerAddress $address): bool
    {
        return $user->id === $address->user_id;
    }
}
