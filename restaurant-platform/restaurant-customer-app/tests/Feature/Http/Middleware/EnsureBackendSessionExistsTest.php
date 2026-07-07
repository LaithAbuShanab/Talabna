<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Stores\AuthTokenStore;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureBackendSessionExistsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'backend.auth'])->get('/__test-protected', fn () => 'protected content');
    }

    public function test_it_redirects_to_login_when_no_token_is_stored(): void
    {
        $this->get('/__test-protected')->assertRedirect(route('login'));
    }

    public function test_it_allows_the_request_through_when_a_token_is_stored(): void
    {
        app(AuthTokenStore::class)->put('a-token');

        $this->get('/__test-protected')->assertOk()->assertSee('protected content');
    }
}
