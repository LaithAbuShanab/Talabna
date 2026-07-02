<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Demo customers with fixed, deterministic emails (customer1@example.com
     * ...) so this seeder is idempotent: re-running it never creates
     * duplicates, it just skips customers that already exist. Each new
     * customer gets 1-2 addresses via CustomerAddressFactory.
     */
    public function run(): void
    {
        $customers = [
            ['name' => 'Sara Ahmad', 'email' => 'customer1@example.com'],
            ['name' => 'Omar Khalil', 'email' => 'customer2@example.com'],
            ['name' => 'Lina Mansour', 'email' => 'customer3@example.com'],
            ['name' => 'Yousef Haddad', 'email' => 'customer4@example.com'],
            ['name' => 'Rana Saleh', 'email' => 'customer5@example.com'],
        ];

        foreach ($customers as $customer) {
            $user = User::query()->where('email', $customer['email'])->first();

            if ($user) {
                continue;
            }

            $user = User::factory()->create([
                'name' => $customer['name'],
                'email' => $customer['email'],
            ]);

            CustomerAddress::factory()->default()->create(['user_id' => $user->id]);
            CustomerAddress::factory()->create(['user_id' => $user->id]);
        }
    }
}
