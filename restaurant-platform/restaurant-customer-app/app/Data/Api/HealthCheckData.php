<?php

declare(strict_types=1);

namespace App\Data\Api;

/**
 * The result of `GET /api/v1/health` (restaurant-backend's health probe —
 * see its routes/api_v1.php). Used by the development-only health-check
 * screen (App\Actions\Api\CheckApiHealthAction) to show whether the
 * backend is reachable, and how long it took to respond.
 */
final readonly class HealthCheckData
{
    public function __construct(
        public string $status,
        public string $timestamp,
        public float $responseTimeMs,
    ) {}

    public static function fromApiResponse(ApiResponseData $response, float $responseTimeMs): self
    {
        return new self(
            status: (string) ($response->data['status'] ?? 'unknown'),
            timestamp: (string) ($response->data['timestamp'] ?? ''),
            responseTimeMs: $responseTimeMs,
        );
    }
}
