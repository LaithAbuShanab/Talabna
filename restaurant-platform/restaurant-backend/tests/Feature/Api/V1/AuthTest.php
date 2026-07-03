<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'device_name' => 'iphone-15',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'role'], 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_register_cannot_set_role_via_mass_assignment(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'device_name' => 'iphone-15',
            'role' => 'admin',
        ]);

        $response->assertCreated()->assertJsonPath('data.user.role', 'customer');
    }

    public function test_register_requires_valid_unique_email_and_confirmed_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'taken@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'different',
            'device_name' => 'iphone-15',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('Password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'Password123',
            'device_name' => 'android-9',
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('Password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
            'device_name' => 'android-9',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_for_a_blocked_account_even_with_the_correct_password(): void
    {
        User::factory()->create([
            'email' => 'blocked@example.com',
            'password' => Hash::make('Password123'),
            'is_active' => false,
            'blocked_reason' => 'Fraud',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'blocked@example.com',
            'password' => 'Password123',
            'device_name' => 'android-9',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever123',
            'device_name' => 'android-9',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_token_is_named_after_the_supplied_device(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123',
            'device_name' => 'my-pixel-phone',
        ])->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'my-pixel-phone',
        ]);
    }

    public function test_logout_revokes_only_the_current_device_token(): void
    {
        $user = User::factory()->create();
        $tokenA = $user->createToken('device-a')->plainTextToken;
        $tokenB = $user->createToken('device-b')->plainTextToken;

        $this->withToken($tokenA)->postJson('/api/v1/auth/logout')->assertOk();

        $this->forgetAuthGuards();
        $this->withToken($tokenA)->getJson('/api/v1/profile')->assertUnauthorized();

        $this->forgetAuthGuards();
        $this->withToken($tokenB)->getJson('/api/v1/profile')->assertOk();
    }

    public function test_logout_all_devices_revokes_every_token(): void
    {
        $user = User::factory()->create();
        $tokenA = $user->createToken('device-a')->plainTextToken;
        $tokenB = $user->createToken('device-b')->plainTextToken;

        $this->withToken($tokenA)->postJson('/api/v1/auth/logout-all-devices')->assertOk();

        $this->forgetAuthGuards();
        $this->withToken($tokenA)->getJson('/api/v1/profile')->assertUnauthorized();

        $this->forgetAuthGuards();
        $this->withToken($tokenB)->getJson('/api/v1/profile')->assertUnauthorized();
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
        $this->getJson('/api/v1/addresses')->assertUnauthorized();
    }
}
