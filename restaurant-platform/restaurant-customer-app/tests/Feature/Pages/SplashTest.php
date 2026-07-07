<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Stores\AuthTokenStore;
use App\Stores\OnboardingStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "استعادة جلسة المستخدم عند فتح التطبيق" — every branch of the Splash
 * screen's session-restoration logic.
 */
class SplashTest extends TestCase
{
    public function test_it_redirects_to_onboarding_on_first_ever_launch(): void
    {
        $this->get('/')->assertRedirect(route('onboarding'));
    }

    public function test_it_redirects_to_login_when_onboarding_is_done_but_there_is_no_token(): void
    {
        app(OnboardingStore::class)->markCompleted();

        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_it_redirects_to_dashboard_when_the_stored_token_is_confirmed_valid(): void
    {
        app(OnboardingStore::class)->markCompleted();
        app(AuthTokenStore::class)->put('a-valid-token');

        Http::fake(['*/profile' => Http::response(['success' => true, 'message' => '', 'data' => [
            'id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com', 'phone' => null,
            'role' => 'customer', 'email_verified_at' => null, 'created_at' => null,
        ]], 200)]);

        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_it_redirects_to_login_and_clears_the_token_when_it_is_confirmed_invalid(): void
    {
        app(OnboardingStore::class)->markCompleted();
        app(AuthTokenStore::class)->put('an-expired-token');

        Http::fake(['*/profile' => Http::response(['success' => false, 'message' => 'Unauthenticated.'], 401)]);

        $this->get('/')->assertRedirect(route('login'));

        $this->assertFalse(app(AuthTokenStore::class)->hasToken());
    }

    public function test_a_transient_connectivity_failure_proceeds_to_dashboard_without_clearing_the_token(): void
    {
        app(OnboardingStore::class)->markCompleted();
        app(AuthTokenStore::class)->put('a-still-valid-token');

        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->get('/')->assertRedirect(route('dashboard'));

        $this->assertTrue(app(AuthTokenStore::class)->hasToken());
    }

    public function test_an_unexpected_server_error_goes_to_the_generic_error_screen(): void
    {
        app(OnboardingStore::class)->markCompleted();
        app(AuthTokenStore::class)->put('a-token');

        Http::fake(['*/profile' => Http::response(['success' => false, 'message' => 'Server error.'], 500)]);

        $this->get('/')->assertRedirect(route('error'));
    }
}
