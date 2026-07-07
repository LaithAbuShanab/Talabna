<?php

declare(strict_types=1);

namespace App\Data\Api;

/**
 * Mirrors restaurant-backend's unified envelope exactly
 * (`{success, message, data}` — see its docs/API_CONVENTIONS.md), so
 * anything that unwraps a successful App\Services\Api\ApiClient call gets
 * a typed, IDE-discoverable shape instead of a raw associative array.
 * Error responses never reach this DTO — ApiClient turns those into a
 * thrown App\Exceptions\Api\ApiHttpException subclass instead.
 */
final readonly class ApiResponseData
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __construct(
        public bool $success,
        public string $message,
        public array $data,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = $payload['data'] ?? [];

        return new self(
            success: (bool) ($payload['success'] ?? false),
            message: (string) ($payload['message'] ?? ''),
            data: is_array($data) ? $data : [],
        );
    }
}
