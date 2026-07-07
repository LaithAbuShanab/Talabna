<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Stores\AuthTokenStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    public function test_it_requires_a_stored_token_to_view(): void
    {
        $this->get('/logout')->assertRedirect(route('login'));
    }

    public function test_it_renders_when_signed_in(): void
    {
        app(AuthTokenStore::class)->put('a-token');

        $this->get('/logout')->assertOk()->assertSeeText('Log out');
    }

    public function test_confirming_clears_the_token_and_redirects_to_login(): void
    {
        Http::fake(['*/auth/logout' => Http::response(['success' => true, 'message' => '', 'data' => []], 200)]);

        app(AuthTokenStore::class)->put('a-token');

        Livewire::test('pages::logout')
            ->call('confirm')
            ->assertRedirect(route('login'));

        $this->assertFalse(app(AuthTokenStore::class)->hasToken());
    }

    public function test_confirming_still_logs_out_locally_even_if_the_server_call_fails(): void
    {
        Http::fake(fn () => throw new ConnectionException('offline'));

        app(AuthTokenStore::class)->put('a-token');

        Livewire::test('pages::logout')
            ->call('confirm')
            ->assertRedirect(route('login'));

        $this->assertFalse(app(AuthTokenStore::class)->hasToken());
    }
}
