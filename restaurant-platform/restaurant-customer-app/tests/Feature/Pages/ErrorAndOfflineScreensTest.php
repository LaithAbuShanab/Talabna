<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use Native\Mobile\Network as NativeNetwork;
use Tests\TestCase;

/**
 * "شاشة خطأ عامة وشاشة offline" — both are plain routes (not gated behind
 * auth), reachable whether or not the visitor is signed in — see
 * routes/web.php.
 */
class ErrorAndOfflineScreensTest extends TestCase
{
    public function test_the_error_screen_renders(): void
    {
        $this->get('/error')->assertOk()->assertSee('Something went wrong');
    }

    public function test_the_offline_screen_redirects_home_when_actually_online(): void
    {
        // No native bridge in tests → NetworkStatusStore assumes online —
        // see that class's own docblock — so mount() should bounce
        // straight back to home.
        $this->get('/offline')->assertRedirect(route('home'));
    }

    public function test_the_offline_screen_renders_when_the_device_is_offline(): void
    {
        $this->app->bind(NativeNetwork::class, fn () => new class
        {
            public function status(): ?object
            {
                return (object) ['connected' => false];
            }
        });

        $this->get('/offline')->assertOk()->assertSee('You are offline');
    }

    /**
     * The route is only ever registered when app()->environment('local')
     * — checked at boot, in routes/web.php — and phpunit.xml sets
     * APP_ENV=testing, so it doesn't exist here at all. This proves the
     * scope boundary that matters: the screen is unreachable outside
     * local development. The positive "it works when local" case was
     * verified manually against a real `APP_ENV=local` server instead,
     * since route registration happens once at boot, before a test
     * method can influence it.
     */
    public function test_the_dev_health_screen_does_not_exist_outside_local(): void
    {
        $this->get('/dev/health')->assertNotFound();
    }
}
