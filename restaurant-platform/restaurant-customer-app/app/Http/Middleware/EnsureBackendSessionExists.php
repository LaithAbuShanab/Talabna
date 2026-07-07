<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Stores\AuthTokenStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level gate for screens that need a signed-in customer (Profile,
 * Change password, Logout confirmation, the placeholder Dashboard) —
 * redirects to Login before even rendering the page if no token is
 * stored. This is a cheap, local check only (does `AuthTokenStore` have
 * *something* stored); it does not call the backend to verify the token
 * is still valid — that happens per-request through
 * App\Services\Api\ApiClient, whose confirmed-401 handling
 * (App\Services\Api\ApiClient::parseResponse()) is the actual source of
 * truth for "this token no longer works", and clears it when that
 * happens. This middleware only prevents rendering a page that would
 * obviously fail immediately for a device that was never logged in (or
 * already logged out) in the first place.
 */
class EnsureBackendSessionExists
{
    public function __construct(private readonly AuthTokenStore $authTokenStore) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->authTokenStore->hasToken()) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
