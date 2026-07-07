<?php

declare(strict_types=1);

namespace Tests\Feature\Pages\Auth;

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    public function test_it_renders(): void
    {
        $this->get('/reset-password')->assertOk()->assertSeeText('Reset password');
    }

    public function test_email_is_prefilled_from_the_query_string(): void
    {
        // mount() reads request()->query('email'), so this needs an
        // actual HTTP request rather than Livewire::test()'s direct
        // component instantiation (which has no query string to read).
        $this->get('/reset-password?email=jane%40example.com')->assertOk()->assertSee('jane@example.com');
    }

    public function test_it_requires_email_token_and_password(): void
    {
        Livewire::test('pages::auth.reset-password')
            ->set('email', '')
            ->set('token', '')
            ->set('password', '')
            ->call('resetPassword')
            ->assertHasErrors(['email', 'token', 'password']);
    }

    public function test_a_successful_reset_shows_the_success_state(): void
    {
        Http::fake(['*/auth/reset-password' => Http::response(['success' => true, 'message' => '', 'data' => []], 200)]);

        Livewire::test('pages::auth.reset-password')
            ->set('email', 'jane@example.com')
            ->set('token', 'the-raw-token')
            ->set('password', 'NewPass1')
            ->set('password_confirmation', 'NewPass1')
            ->call('resetPassword')
            ->assertSet('done', true);
    }

    public function test_an_invalid_token_shows_the_servers_error(): void
    {
        Http::fake(['*/auth/reset-password' => Http::response([
            'success' => false,
            'message' => 'This password reset token is invalid.',
            'errors' => ['email' => ['This password reset token is invalid.']],
        ], 422)]);

        Livewire::test('pages::auth.reset-password')
            ->set('email', 'jane@example.com')
            ->set('token', 'wrong-token')
            ->set('password', 'NewPass1')
            ->set('password_confirmation', 'NewPass1')
            ->call('resetPassword')
            ->assertHasErrors(['email'])
            ->assertSet('done', false);
    }
}
