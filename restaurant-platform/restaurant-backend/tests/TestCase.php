<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Auth guards memoize the resolved user for the lifetime of the
     * application instance, which normally matches one real HTTP request.
     * A single test that simulates several requests authenticated as
     * different users/tokens (e.g. two devices) reuses the same
     * application instance, so a stale cached user would otherwise leak
     * from one simulated request into the next. Call this between such
     * requests to force re-authentication from the (now current) request.
     */
    protected function forgetAuthGuards(): void
    {
        $this->app->make('auth')->forgetGuards();
    }
}
