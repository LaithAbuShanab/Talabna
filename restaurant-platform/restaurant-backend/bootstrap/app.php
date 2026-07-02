<?php

declare(strict_types=1);

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // This backend has no web "login" route — the admin panel authenticates
        // through Filament's own guard, and everything else is API-only. Without
        // this, Laravel's default guest redirect tries to route('login') and
        // throws a RouteNotFoundException instead of a clean 401.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Every exception raised on an api/* route is rendered through the
        // platform's unified {success, message, errors} envelope, and never
        // exposes a stack trace or internal exception details in production.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error(
                    'The given data was invalid.',
                    $e->errors(),
                    $e->status,
                ),
                $e instanceof AuthenticationException => ApiResponse::error(
                    'Unauthenticated.',
                    status: 401,
                ),
                $e instanceof AuthorizationException => ApiResponse::error(
                    $e->getMessage() ?: 'This action is unauthorized.',
                    status: $e->status() ?: 403,
                ),
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error(
                    'The requested resource was not found.',
                    status: 404,
                ),
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    $e->getMessage() ?: 'Request failed.',
                    status: $e->getStatusCode(),
                ),
                default => ApiResponse::error(
                    app()->hasDebugModeEnabled() ? $e->getMessage() : 'Server Error.',
                    status: 500,
                ),
            };
        });
    })->create();
