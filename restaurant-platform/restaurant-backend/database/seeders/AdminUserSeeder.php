<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * One demo admin account for logging into /admin locally. Password is
     * the well-known Laravel dev-seed placeholder ("password", see
     * UserFactory) — never a real credential, and documented as such in
     * restaurant-backend/README.dev.md (development only).
     */
    public function run(): void
    {
        if (User::query()->where('email', 'admin@example.com')->exists()) {
            return;
        }

        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);
    }
}
