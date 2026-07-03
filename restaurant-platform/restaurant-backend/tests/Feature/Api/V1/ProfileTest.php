<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_authenticated_user_can_update_their_name_and_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/profile', [
            'name' => 'New Name',
            'email' => 'new-email@example.com',
        ])->assertOk()->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new-email@example.com']);
    }

    public function test_authenticated_user_can_set_their_phone_number(): void
    {
        $user = User::factory()->create(['phone' => null]);

        $this->actingAs($user)->putJson('/api/v1/profile', [
            'phone' => '+962790000000',
        ])->assertOk()->assertJsonPath('data.phone', '+962790000000');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '+962790000000']);
    }

    public function test_profile_update_rejects_an_email_already_used_by_another_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $this->actingAs($user)->putJson('/api/v1/profile', [
            'email' => 'taken@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123')]);

        $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'OldPassword123',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'NewPassword123',
            'device_name' => 'x',
        ])->assertOk();
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123')]);

        $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['current_password']);
    }

    public function test_change_password_revokes_other_device_tokens_but_keeps_current_session(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123')]);
        $otherToken = $user->createToken('other-device')->plainTextToken;
        $currentToken = $user->createToken('current-device')->plainTextToken;

        $this->withToken($currentToken)->putJson('/api/v1/profile/password', [
            'current_password' => 'OldPassword123',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk();

        $this->forgetAuthGuards();
        $this->withToken($otherToken)->getJson('/api/v1/profile')->assertUnauthorized();

        $this->forgetAuthGuards();
        $this->withToken($currentToken)->getJson('/api/v1/profile')->assertOk();
    }
}
