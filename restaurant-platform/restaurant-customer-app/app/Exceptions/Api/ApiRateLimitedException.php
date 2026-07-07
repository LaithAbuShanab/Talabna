<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * 429 — restaurant-backend's own rate limiting rejected the request (see
 * restaurant-backend/docs/API_CONVENTIONS.md and its per-endpoint
 * limiters). Never retried automatically (retrying into a rate limit only
 * makes it worse) — `retryAfterSeconds` is exposed so a caller can show
 * "try again in Ns" if it has the `Retry-After` header to read.
 */
final class ApiRateLimitedException extends ApiHttpException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        int $statusCode,
        array $body,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message, $statusCode, $body);
    }
}
