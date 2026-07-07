<?php

declare(strict_types=1);

namespace Tests\Feature\Pages\Auth;

use App\Stores\AuthTokenStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
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
            'created_at' => null,
        ];
    }

    public function test_it_renders(): void
    {
        $this->get('/login')->assertOk()->assertSeeText('Log in');
    }

    public function test_it_requires_email_and_password(): void
    {
        Livewire::test('pages::auth.login')
            ->set('email', '')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['email' => 'required', 'password' => 'required']);
    }

    public function test_successful_login_stores_the_token_and_redirects(): void
    {
        Http::fake(['*/auth/login' => Http::response([
            'success' => true, 'message' => '', 'data' => ['user' => $this->fakeUser(), 'token' => '1|abc'],
        ], 200)]);

        Livewire::test('pages::auth.login')
            ->set('email', 'jane@example.com')
            ->set('password', 'Password1')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertSame('1|abc', app(AuthTokenStore::class)->token());
    }

    public function test_a_422_shows_the_servers_field_error(): void
    {
        Http::fake(['*/auth/login' => Http::response([
            'success' => false,
            'message' => 'Invalid.',
            'errors' => ['email' => ['These credentials do not match our records.']],
        ], 422)]);

        Livewire::test('pages::auth.login')
            ->set('email', 'jane@example.com')
            ->set('password', 'wrong')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertFalse(app(AuthTokenStore::class)->hasToken());
    }

    public function test_a_connectivity_failure_shows_a_general_error_not_a_field_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('offline'));

        Livewire::test('pages::auth.login')
            ->set('email', 'jane@example.com')
            ->set('password', 'Password1')
            ->call('login')
            ->assertHasNoErrors()
            ->assertSet('generalError', fn (?string $value) => $value !== null);
    }
}
