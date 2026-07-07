<?php

declare(strict_types=1);

namespace App\Actions\Api;

use App\Data\Api\HealthCheckData;
use App\Exceptions\Api\ApiException;
use App\Services\Api\ApiClient;

/**
 * Calls restaurant-backend's `GET /api/v1/health` and times the round
 * trip — used by the development-only health-check screen
 * (`resources/views/pages/dev/⚡health.blade.php`) so a developer can
 * confirm the configured `RESTAURANT_BACKEND_URL` is actually reachable
 * without digging through logs. Lets any App\Exceptions\Api\ApiException
 * propagate — the screen itself decides how to display a failure.
 */
final class CheckApiHealthAction
{
    public function __construct(private readonly ApiClient $client) {}

    /**
     * @throws ApiException
     */
    public function execute(): HealthCheckData
    {
        $start = microtime(true);

        $response = $this->client->get('/api/v1/health');

        $responseTimeMs = round((microtime(true) - $start) * 1000, 1);

        return HealthCheckData::fromApiResponse($response, $responseTimeMs);
    }
}
