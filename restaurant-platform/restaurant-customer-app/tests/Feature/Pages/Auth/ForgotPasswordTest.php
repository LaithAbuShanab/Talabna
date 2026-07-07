<?php

declare(strict_types=1);

namespace Tests\Feature\Pages\Auth;

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    public function test_it_renders(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSeeText('Forgot password');
    }

    public function test_it_requires_a_valid_email(): void
    {
        Livewire::test('pages::auth.forgot-password')
            ->set('email', 'not-an-email')
            ->call('sendResetCode')
            ->assertHasErrors(['email']);
    }

    public function test_it_shows_the_same_generic_message_regardless_of_whether_the_account_exists(): void
    {
        Http::fake(['*/auth/forgot-password' => Http::response(['success' => true, 'message' => '', 'data' => []], 200)]);

        Livewire::test('pages::auth.forgot-password')
            ->set('email', 'anyone@example.com')
            ->call('sendResetCode')
            ->assertSet('sent', true)
            ->assertHasNoErrors();
    }
}
