<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class ApiResponseFormatTest extends TestCase
{
    public function test_unauthenticated_api_request_returns_unified_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ])
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_unauthenticated_api_request_without_accept_header_still_returns_json_401(): void
    {
        // Regression guard: a plain request (no "Accept: application/json"),
        // unlike getJson(), used to trigger Laravel's default guest redirect
        // to a "login" route that doesn't exist in this API-only backend,
        // producing a 500 instead of a clean 401. See bootstrap/app.php's
        // redirectGuestsTo(fn () => null).
        $response = $this->get('/api/v1/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_unknown_api_route_returns_unified_error_envelope(): void
    {
        $response = $this->getJson('/api/this-route-does-not-exist');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'The requested resource was not found.',
            ])
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_api_error_response_never_leaks_a_stack_trace(): void
    {
        $response = $this->getJson('/api/this-route-does-not-exist');

        $response->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line');
    }
}
