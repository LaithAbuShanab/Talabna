<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * The backend *did* respond — just with an error status. Carries the raw
 * status code and decoded body (when it was valid JSON) for callers that
 * want more than the generic error screen (e.g. reading a field-level
 * validation message). See ApiConnectivityException for the "no response
 * at all" category instead.
 */
abstract class ApiHttpException extends ApiException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly array $body = [],
    ) {
        parent::__construct($message);
    }
}
