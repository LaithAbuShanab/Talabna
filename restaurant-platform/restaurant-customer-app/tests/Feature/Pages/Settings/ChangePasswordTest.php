<?php

declare(strict_types=1);

namespace Tests\Feature\Pages\Settings;

use App\Stores\AuthTokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    public function test_it_requires_a_stored_token_to_view(): void
    {
        $this->get('/settings/change-password')->assertRedirect(route('login'));
    }

    public function test_it_requires_current_and_new_password(): void
    {
        app(AuthTokenStore::class)->put('a-token');

        Livewire::test('pages::settings.change-password')
            ->set('current_password', '')
            ->set('password', '')
            ->call('save')
            ->assertHasErrors(['current_password', 'password']);
    }

    public function test_saving_succeeds_and_clears_the_form(): void
    {
        app(AuthTokenStore::class)->put('a-token');
        Http::fake(['*/profile/password' => Http::response(['success' => true, 'message' => '', 'data' => []], 200)]);

        Livewire::test('pages::settings.change-password')
            ->set('current_password', 'OldPass1')
            ->set('password', 'NewPass1')
            ->set('password_confirmation', 'NewPass1')
            ->call('save')
            ->assertSet('saved', true)
            ->assertSet('current_password', '')
            ->assertSet('password', '')
            ->assertHasNoErrors();
    }

    public function test_the_wrong_current_password_shows_the_servers_field_error(): void
    {
        app(AuthTokenStore::class)->put('a-token');
        Http::fake(['*/profile/password' => Http::response([
            'success' => false,
            'message' => 'Invalid.',
            'errors' => ['current_password' => ['The provided password is incorrect.']],
        ], 422)]);

        Livewire::test('pages::settings.change-password')
            ->set('current_password', 'WrongPass1')
            ->set('password', 'NewPass1')
            ->set('password_confirmation', 'NewPass1')
            ->call('save')
            ->assertHasErrors(['current_password'])
            ->assertSet('saved', false);
    }
}
