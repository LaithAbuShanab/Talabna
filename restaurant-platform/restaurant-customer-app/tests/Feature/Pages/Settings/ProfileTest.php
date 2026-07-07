<?php

declare(strict_types=1);

namespace Tests\Feature\Pages\Settings;

use App\Stores\AuthTokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    private function fakeUser(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => null,
            'role' => 'customer',
            'email_verified_at' => null,
            'created_at' => null,
        ], $overrides);
    }

    public function test_it_requires_a_stored_token_to_view(): void
    {
        $this->get('/settings/profile')->assertRedirect(route('login'));
    }

    public function test_it_loads_the_current_profile_on_mount(): void
    {
        app(AuthTokenStore::class)->put('a-token');
        Http::fake(['*/profile' => Http::response(['success' => true, 'message' => '', 'data' => $this->fakeUser()], 200)]);

        Livewire::test('pages::settings.profile')
            ->assertSet('name', 'Jane Doe')
            ->assertSet('email', 'jane@example.com');
    }

    public function test_saving_updates_the_profile(): void
    {
        app(AuthTokenStore::class)->put('a-token');
        Http::fake(function ($request) {
            return $request->method() === 'GET'
                ? Http::response(['success' => true, 'message' => '', 'data' => $this->fakeUser()], 200)
                : Http::response(['success' => true, 'message' => '', 'data' => $this->fakeUser(['name' => 'New Name'])], 200);
        });

        Livewire::test('pages::settings.profile')
            ->set('name', 'New Name')
            ->call('save')
            ->assertSet('saved', true)
            ->assertHasNoErrors();
    }

    public function test_a_confirmed_401_while_saving_redirects_to_login(): void
    {
        app(AuthTokenStore::class)->put('a-now-invalid-token');
        Http::fake(function ($request) {
            return $request->method() === 'GET'
                ? Http::response(['success' => true, 'message' => '', 'data' => $this->fakeUser()], 200)
                : Http::response(['success' => false, 'message' => 'Unauthenticated.'], 401);
        });

        Livewire::test('pages::settings.profile')
            ->set('name', 'New Name')
            ->call('save')
            ->assertRedirect(route('login'));

        $this->assertFalse(app(AuthTokenStore::class)->hasToken());
    }
}
