<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * The request was actually attempted but didn't get a response within
 * `config('api.restaurant_backend.timeout')` seconds — detected from
 * Laravel's Illuminate\Http\Client\ConnectionException message (Guzzle
 * doesn't expose a distinct exception type for "timed out" vs. "couldn't
 * connect at all", so App\Services\Api\ApiClient classifies by message —
 * see its docblock for the exact heuristic and its accepted limits).
 */
final class ApiTimeoutException extends ApiConnectivityException {}
