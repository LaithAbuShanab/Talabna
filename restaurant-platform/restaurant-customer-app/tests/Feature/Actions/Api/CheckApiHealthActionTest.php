<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Api;

use App\Actions\Api\CheckApiHealthAction;
use App\Exceptions\Api\ApiServerException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckApiHealthActionTest extends TestCase
{
    public function test_it_returns_the_backend_status_and_a_response_time(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => ['status' => 'ok', 'timestamp' => '2026-07-06T12:00:00+00:00'],
        ], 200)]);

        $result = app(CheckApiHealthAction::class)->execute();

        $this->assertSame('ok', $result->status);
        $this->assertSame('2026-07-06T12:00:00+00:00', $result->timestamp);
        $this->assertGreaterThanOrEqual(0.0, $result->responseTimeMs);
    }

    public function test_it_lets_an_api_exception_propagate(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Down.'], 500)]);

        $this->expectException(ApiServerException::class);

        app(CheckApiHealthAction::class)->execute();
    }
}
