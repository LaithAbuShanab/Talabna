<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * 422 — restaurant-backend's unified `{success: false, message, errors}`
 * envelope (see restaurant-backend/docs/API_CONVENTIONS.md). `errors` is
 * usually a per-field validation map (`{field: [messages]}`), but the
 * backend also uses 422 for business-rule failures with a single
 * `{code: "..."}` shape (e.g. cart pricing) — carried as-is rather than
 * assuming one shape, so callers should check what's actually in it.
 */
final class ApiValidationException extends ApiHttpException
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        int $statusCode,
        array $body,
        public readonly array $errors,
    ) {
        parent::__construct($message, $statusCode, $body);
    }
}
