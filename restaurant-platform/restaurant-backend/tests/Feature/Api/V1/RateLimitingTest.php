<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => Hash::make('Password123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'wrong-password',
                'device_name' => 'x',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
            'device_name' => 'x',
        ])->assertStatus(429);
    }

    public function test_forgot_password_is_rate_limited_after_three_attempts(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'jane@example.com'])->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'jane@example.com'])->assertStatus(429);
    }
}
