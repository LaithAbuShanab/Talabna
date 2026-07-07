<?php

declare(strict_types=1);

namespace Tests\Feature\Pages\Auth;

use App\Stores\AuthTokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTest extends TestCase
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
        $this->get('/register')->assertOk()->assertSeeText('Create an account');
    }

    public function test_password_must_meet_the_complexity_rule_locally(): void
    {
        Livewire::test('pages::auth.register')
            ->set('name', 'Jane')
            ->set('email', 'jane@example.com')
            ->set('password', 'short')
            ->set('password_confirmation', 'short')
            ->call('register')
            ->assertHasErrors(['password']);
    }

    public function test_password_confirmation_must_match(): void
    {
        Livewire::test('pages::auth.register')
            ->set('name', 'Jane')
            ->set('email', 'jane@example.com')
            ->set('password', 'Password1')
            ->set('password_confirmation', 'Password2')
            ->call('register')
            ->assertHasErrors(['password']);
    }

    public function test_successful_registration_stores_the_token_and_redirects(): void
    {
        Http::fake(['*/auth/register' => Http::response([
            'success' => true, 'message' => '', 'data' => ['user' => $this->fakeUser(), 'token' => '1|abc'],
        ], 201)]);

        Livewire::test('pages::auth.register')
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->set('password', 'Password1')
            ->set('password_confirmation', 'Password1')
            ->call('register')
            ->assertRedirect(route('dashboard'));

        $this->assertSame('1|abc', app(AuthTokenStore::class)->token());
    }

    public function test_a_taken_email_shows_the_servers_field_error(): void
    {
        Http::fake(['*/auth/register' => Http::response([
            'success' => false,
            'message' => 'Invalid.',
            'errors' => ['email' => ['The email has already been taken.']],
        ], 422)]);

        Livewire::test('pages::auth.register')
            ->set('name', 'Jane Doe')
            ->set('email', 'taken@example.com')
            ->set('password', 'Password1')
            ->set('password_confirmation', 'Password1')
            ->call('register')
            ->assertHasErrors(['email']);
    }
}
