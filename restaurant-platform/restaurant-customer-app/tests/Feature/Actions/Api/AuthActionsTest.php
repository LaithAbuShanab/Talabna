<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Api;

use App\Actions\Api\ChangePasswordAction;
use App\Actions\Api\FetchProfileAction;
use App\Actions\Api\ForgotPasswordAction;
use App\Actions\Api\LoginAction;
use App\Actions\Api\LogoutAction;
use App\Actions\Api\RegisterAction;
use App\Actions\Api\ResetPasswordAction;
use App\Actions\Api\UpdateProfileAction;
use App\Exceptions\Api\ApiConnectionException;
use App\Exceptions\Api\ApiValidationException;
use App\Stores\AuthTokenStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthActionsTest extends TestCase
{
    private function fakeUser(): array
    {
        return [
            'id' => 1,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => null,
            'role' => 'customer',
            'email_verified_at' => null,
            'created_at' => '2026-07-07T00:00:00+00:00',
        ];
    }

    public function test_register_stores_the_returned_token(): void
    {
        Http::fake(['*/auth/register' => Http::response([
            'success' => true,
            'message' => 'Registered.',
            'data' => ['user' => $this->fakeUser(), 'token' => '1|abc'],
        ], 201)]);

        $result = app(RegisterAction::class)->execute('Jane Doe', 'jane@example.com', 'Password1', 'Password1', 'iPhone 15');

        $this->assertSame('jane@example.com', $result->user->email);
        $this->assertSame('1|abc', $result->token);
        $this->assertSame('1|abc', app(AuthTokenStore::class)->token());
    }

    public function test_register_sends_every_required_field(): void
    {
        Http::fake(['*/auth/register' => Http::response([
            'success' => true, 'message' => '', 'data' => ['user' => $this->fakeUser(), 'token' => 't'],
        ], 201)]);

        app(RegisterAction::class)->execute('Jane Doe', 'jane@example.com', 'Password1', 'Password1', 'iPhone 15');

        Http::assertSent(fn ($request) => $request['name'] === 'Jane Doe'
            && $request['email'] === 'jane@example.com'
            && $request['password'] === 'Password1'
            && $request['password_confirmation'] === 'Password1'
            && $request['device_name'] === 'iPhone 15');
    }

    public function test_register_surfaces_validation_errors(): void
    {
        Http::fake(['*/auth/register' => Http::response([
            'success' => false,
            'message' => 'Invalid.',
            'errors' => ['email' => ['The email has already been taken.']],
        ], 422)]);

        try {
            app(RegisterAction::class)->execute('Jane', 'taken@example.com', 'Password1', 'Password1', 'iPhone');
            $this->fail('Expected ApiValidationException.');
        } catch (ApiValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors);
        }

        $this->assertFalse(app(AuthTokenStore::class)->hasToken());
    }

    public function test_login_stores_the_returned_token(): void
    {
        Http::fake(['*/auth/login' => Http::response([
            'success' => true,
            'message' => 'Logged in.',
            'data' => ['user' => $this->fakeUser(), 'token' => '2|def'],
        ], 200)]);

        $result = app(LoginAction::class)->execute('jane@example.com', 'Password1', 'iPhone 15');

        $this->assertSame('2|def', $result->token);
        $this->assertTrue(app(AuthTokenStore::class)->hasToken());
    }

    public function test_login_does_not_store_a_token_on_failure(): void
    {
        Http::fake(['*/auth/login' => Http::response([
            'success' => false,
            'message' => 'Invalid.',
            'errors' => ['email' => ['These credentials do not match our records.']],
        ], 422)]);

        try {
            app(LoginAction::class)->execute('jane@example.com', 'wrong', 'iPhone');
        } catch (ApiValidationException) {
            // expected
        }

        $this->assertFalse(app(AuthTokenStore::class)->hasToken());
    }

    public function test_login_propagates_a_connectivity_exception_without_storing_anything(): void
    {
        Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

        $this->expectException(ApiConnectionException::class);

        app(LoginAction::class)->execute('jane@example.com', 'Password1', 'iPhone');
    }

    public function test_forgot_password_always_succeeds_regardless_of_account_existence(): void
    {
        Http::fake(['*/auth/forgot-password' => Http::response(['success' => true, 'message' => 'Sent.', 'data' => []], 200)]);

        app(ForgotPasswordAction::class)->execute('anyone@example.com');

        Http::assertSent(fn ($request) => $request['email'] === 'anyone@example.com');
    }

    public function test_reset_password_sends_the_manually_entered_token(): void
    {
        Http::fake(['*/auth/reset-password' => Http::response(['success' => true, 'message' => 'Reset.', 'data' => []], 200)]);

        app(ResetPasswordAction::class)->execute('jane@example.com', 'the-raw-token-from-email', 'NewPass1', 'NewPass1');

        Http::assertSent(fn ($request) => $request['token'] === 'the-raw-token-from-email'
            && $request['email'] === 'jane@example.com'
            && $request['password'] === 'NewPass1');
    }

    public function test_logout_clears_the_local_token_on_success(): void
    {
        Http::fake(['*/auth/logout' => Http::response(['success' => true, 'message' => 'Logged out.', 'data' => []], 200)]);

        app(AuthTokenStore::class)->put('a-token');

        app(LogoutAction::class)->execute();

        $this->assertFalse(app(AuthTokenStore::class)->hasToken());
    }

    public function test_logout_still_clears_the_local_token_even_if_the_request_fails(): void
    {
        Http::fake(fn () => throw new ConnectionException('offline'));

        app(AuthTokenStore::class)->put('a-token');

        app(LogoutAction::class)->execute();

        $this->assertFalse(app(AuthTokenStore::class)->hasToken());
    }

    public function test_fetch_profile_returns_the_current_user(): void
    {
        Http::fake(['*/profile' => Http::response(['success' => true, 'message' => '', 'data' => $this->fakeUser()], 200)]);

        $user = app(FetchProfileAction::class)->execute();

        $this->assertSame('jane@example.com', $user->email);
    }

    public function test_update_profile_sends_only_the_given_fields(): void
    {
        Http::fake(['*/profile' => Http::response(['success' => true, 'message' => '', 'data' => $this->fakeUser()], 200)]);

        app(UpdateProfileAction::class)->execute(['name' => 'New Name']);

        Http::assertSent(fn ($request) => $request['name'] === 'New Name' && ! isset($request['email']));
    }

    public function test_change_password_sends_current_and_new_password(): void
    {
        Http::fake(['*/profile/password' => Http::response(['success' => true, 'message' => '', 'data' => []], 200)]);

        app(ChangePasswordAction::class)->execute('OldPass1', 'NewPass1', 'NewPass1');

        Http::assertSent(fn ($request) => $request['current_password'] === 'OldPass1'
            && $request['password'] === 'NewPass1'
            && $request['password_confirmation'] === 'NewPass1');
    }
}
