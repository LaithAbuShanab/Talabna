<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense in depth for a blocked account: App\Services\CustomerBlockingService
 * already revokes every Sanctum token the moment an account is blocked, so
 * this middleware mostly guards the gap between "blocked" and "token
 * revoked" (there is none, in practice — same transaction) and any future
 * write path that might set `is_active = false` without going through that
 * service. Applied to every authenticated customer-facing route (see
 * routes/api_v1.php) alongside `auth:sanctum`.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBlocked()) {
            abort(403, trans('auth.account_blocked'));
        }

        return $next($request);
    }
}
