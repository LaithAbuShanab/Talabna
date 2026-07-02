<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * One demo account per admin role, for logging into /admin locally and
     * exercising the role/policy matrix. Password is the well-known
     * Laravel dev-seed placeholder ("password", see UserFactory) — never a
     * real credential, and documented as such in
     * restaurant-backend/README.dev.md (development only).
     */
    public function run(): void
    {
        $accounts = [
            'admin@example.com' => ['name' => 'Super Admin', 'role' => UserRole::SuperAdmin],
            'manager@example.com' => ['name' => 'Manager', 'role' => UserRole::Manager],
            'kitchen@example.com' => ['name' => 'Kitchen Staff', 'role' => UserRole::Kitchen],
            'cashier@example.com' => ['name' => 'Cashier', 'role' => UserRole::Cashier],
            'support@example.com' => ['name' => 'Support', 'role' => UserRole::Support],
        ];

        foreach ($accounts as $email => $attributes) {
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }

            $user = User::factory()->create([
                'name' => $attributes['name'],
                'email' => $email,
            ]);

            $user->forceFill(['role' => $attributes['role'], 'is_active' => true])->save();
        }
    }
}
