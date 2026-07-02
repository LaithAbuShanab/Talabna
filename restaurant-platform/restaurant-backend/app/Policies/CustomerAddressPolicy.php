<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerAddress;
use App\Models\User;

class CustomerAddressPolicy
{
    public function view(User $user, CustomerAddress $address): bool
    {
        return $user->id === $address->user_id;
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
