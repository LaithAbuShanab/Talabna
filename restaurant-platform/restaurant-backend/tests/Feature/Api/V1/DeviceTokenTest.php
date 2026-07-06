<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\DevicePlatform;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_a_device_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/device-tokens', [
            'token' => 'expo-push-token-abc',
            'platform' => 'android',
            'device_name' => "Laith's Pixel",
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'expo-push-token-abc',
            'platform' => DevicePlatform::Android->value,
            'device_name' => "Laith's Pixel",
            'is_active' => 1,
        ]);
    }

    public function test_registering_the_same_token_again_reassigns_it_to_the_current_user(): void
    {
        $previousOwner = User::factory()->create();
        $newOwner = User::factory()->create();
        DeviceToken::factory()->for($previousOwner)->create(['token' => 'shared-device-token']);

        $response = $this->actingAs($newOwner)->postJson('/api/v1/device-tokens', [
            'token' => 'shared-device-token',
            'platform' => 'ios',
        ]);

        $response->assertCreated();
        $this->assertSame(1, DeviceToken::query()->where('token', 'shared-device-token')->count());
        $this->assertDatabaseHas('device_tokens', ['token' => 'shared-device-token', 'user_id' => $newOwner->id]);
    }

    public function test_token_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/device-tokens', [
            'platform' => 'android',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['token']);
    }

    public function test_platform_must_be_a_valid_enum_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/device-tokens', [
            'token' => 'some-token',
            'platform' => 'windows-phone',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['platform']);
    }

    public function test_guests_cannot_register_a_device_token(): void
    {
        $response = $this->postJson('/api/v1/device-tokens', [
            'token' => 'some-token',
            'platform' => 'android',
        ]);

        $response->assertUnauthorized();
    }

    public function test_a_user_can_deactivate_their_own_device_token(): void
    {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->for($user)->create(['token' => 'to-remove', 'is_active' => true]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/device-tokens', ['token' => 'to-remove']);

        $response->assertOk();
        $this->assertFalse($token->fresh()->is_active);
    }

    public function test_a_user_cannot_deactivate_another_users_device_token(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $token = DeviceToken::factory()->for($owner)->create(['token' => 'someone-elses-token', 'is_active' => true]);

        $response = $this->actingAs($attacker)->deleteJson('/api/v1/device-tokens', ['token' => 'someone-elses-token']);

        $response->assertOk();
        $this->assertTrue($token->fresh()->is_active);
    }
}
