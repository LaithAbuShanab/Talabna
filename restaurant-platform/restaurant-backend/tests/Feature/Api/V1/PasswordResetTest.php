<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_identical_response_for_existing_and_unknown_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $existing = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'jane@example.com']);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $existing->assertOk();
        $unknown->assertOk();
        $this->assertSame($existing->json(), $unknown->json());
    }

    public function test_forgot_password_actually_creates_a_reset_token_for_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'jane@example.com'])->assertOk();

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'jane@example.com',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'NewPassword123',
            'device_name' => 'x',
        ])->assertOk();
    }

    public function test_reset_password_fails_with_an_invalid_token(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'jane@example.com',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_resetting_password_revokes_existing_tokens(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $existingToken = $user->createToken('old-device')->plainTextToken;
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'jane@example.com',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk();

        $this->withToken($existingToken)->getJson('/api/v1/profile')->assertUnauthorized();
    }
}
