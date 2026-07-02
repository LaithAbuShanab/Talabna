<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'status' => 'ok',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['status', 'timestamp'],
            ]);
    }
}
