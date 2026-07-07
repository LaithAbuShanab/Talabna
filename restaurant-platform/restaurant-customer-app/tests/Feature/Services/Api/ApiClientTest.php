<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Api;

use App\Exceptions\Api\ApiConnectionException;
use App\Exceptions\Api\ApiOfflineException;
use App\Exceptions\Api\ApiRateLimitedException;
use App\Exceptions\Api\ApiServerException;
use App\Exceptions\Api\ApiTimeoutException;
use App\Exceptions\Api\ApiUnauthorizedException;
use App\Exceptions\Api\ApiUnexpectedResponseException;
use App\Exceptions\Api\ApiValidationException;
use App\Services\Api\ApiClient;
use App\Stores\AuthTokenStore;
use App\Stores\NetworkStatusStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Network as NativeNetwork;
use RuntimeException;
use Tests\TestCase;

/**
 * "أنشئ tests للـ API client باستخدام HTTP fakes" — every scenario here
 * uses Illuminate\Support\Facades\Http::fake(), never a real network call.
 * See docs/CUSTOMER_APP_API_CLIENT.md for the contract this pins down.
 */
class ApiClientTest extends TestCase
{
    private function client(): ApiClient
    {
        return app(ApiClient::class);
    }

    // --- Success -------------------------------------------------------

    public function test_a_successful_get_returns_a_typed_response(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => ['status' => 'ok'],
        ], 200)]);

        $result = $this->client()->get('/api/v1/health');

        $this->assertTrue($result->success);
        $this->assertSame('OK', $result->message);
        $this->assertSame('ok', $result->data['status']);
    }

    public function test_post_sends_the_body_as_json(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'message' => '', 'data' => []], 201)]);

        $this->client()->post('/api/v1/orders', ['delivery_type' => 'pickup']);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request['delivery_type'] === 'pickup';
        });
    }

    // --- Connectivity ("انقطاع الإنترنت" و timeout) ----------------------

    public function test_it_throws_offline_without_ever_calling_http_when_the_device_is_known_offline(): void
    {
        $this->app->bind(NativeNetwork::class, fn () => new class
        {
            public function status(): ?object
            {
                return (object) ['connected' => false];
            }
        });

        Http::fake();

        $this->expectException(ApiOfflineException::class);

        try {
            $this->client()->get('/api/v1/health');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_maps_a_timeout_to_a_specific_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 15000 milliseconds'));

        $this->expectException(ApiTimeoutException::class);

        $this->client()->get('/api/v1/health');
    }

    public function test_it_maps_a_non_timeout_connection_failure_to_a_generic_connection_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host'));

        $this->expectException(ApiConnectionException::class);

        $this->client()->get('/api/v1/health');
    }

    // --- HTTP error statuses ---------------------------------------------

    public function test_401_maps_to_unauthorized(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Unauthenticated.'], 401)]);

        try {
            $this->client()->get('/api/v1/orders');
            $this->fail('Expected ApiUnauthorizedException.');
        } catch (ApiUnauthorizedException $e) {
            $this->assertSame(401, $e->statusCode);
            $this->assertSame('Unauthenticated.', $e->getMessage());
        }
    }

    // --- "حذف token عند 401 المؤكد" / "عدم حذف الجلسة بسبب timeout عابر" ---

    public function test_a_confirmed_401_clears_the_stored_token(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Unauthenticated.'], 401)]);

        app(AuthTokenStore::class)->put('a-now-invalid-token');

        try {
            $this->client()->get('/api/v1/profile');
            $this->fail('Expected ApiUnauthorizedException.');
        } catch (ApiUnauthorizedException) {
            $this->assertFalse(app(AuthTokenStore::class)->hasToken());
        }
    }

    public function test_a_403_never_clears_the_stored_token(): void
    {
        // 403 means "this token is valid, but not allowed to do *this*"
        // (e.g. another user's resource) — a completely different problem
        // from an invalid token, and must never log a good session out.
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Forbidden.'], 403)]);

        app(AuthTokenStore::class)->put('a-perfectly-valid-token');

        try {
            $this->client()->get('/api/v1/orders/999');
            $this->fail('Expected ApiUnauthorizedException.');
        } catch (ApiUnauthorizedException) {
            $this->assertTrue(app(AuthTokenStore::class)->hasToken());
            $this->assertSame('a-perfectly-valid-token', app(AuthTokenStore::class)->token());
        }
    }

    public function test_a_transient_timeout_never_clears_the_stored_token(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        app(AuthTokenStore::class)->put('a-still-valid-token');

        try {
            $this->client()->get('/api/v1/profile');
            $this->fail('Expected ApiTimeoutException.');
        } catch (ApiTimeoutException) {
            $this->assertTrue(app(AuthTokenStore::class)->hasToken());
        }
    }

    public function test_being_offline_never_clears_the_stored_token(): void
    {
        $this->app->bind(NativeNetwork::class, fn () => new class
        {
            public function status(): ?object
            {
                return (object) ['connected' => false];
            }
        });

        app(AuthTokenStore::class)->put('a-still-valid-token');

        try {
            $this->client()->get('/api/v1/profile');
            $this->fail('Expected ApiOfflineException.');
        } catch (ApiOfflineException) {
            $this->assertTrue(app(AuthTokenStore::class)->hasToken());
        }
    }

    public function test_403_also_maps_to_unauthorized(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Forbidden.'], 403)]);

        try {
            $this->client()->get('/api/v1/orders');
            $this->fail('Expected ApiUnauthorizedException.');
        } catch (ApiUnauthorizedException $e) {
            $this->assertSame(403, $e->statusCode);
        }
    }

    public function test_422_maps_to_validation_with_the_field_errors(): void
    {
        Http::fake(['*' => Http::response([
            'success' => false,
            'message' => 'The given data was invalid.',
            'errors' => ['email' => ['The email field is required.']],
        ], 422)]);

        try {
            $this->client()->post('/api/v1/auth/register', []);
            $this->fail('Expected ApiValidationException.');
        } catch (ApiValidationException $e) {
            $this->assertSame(422, $e->statusCode);
            $this->assertSame(['email' => ['The email field is required.']], $e->errors);
        }
    }

    public function test_429_maps_to_rate_limited_with_retry_after(): void
    {
        Http::fake(['*' => Http::response(
            ['success' => false, 'message' => 'Too many requests.'],
            429,
            ['Retry-After' => '30'],
        )]);

        try {
            $this->client()->get('/api/v1/health');
            $this->fail('Expected ApiRateLimitedException.');
        } catch (ApiRateLimitedException $e) {
            $this->assertSame(30, $e->retryAfterSeconds);
        }
    }

    public function test_429_without_a_retry_after_header_has_a_null_value(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Too many requests.'], 429)]);

        try {
            $this->client()->get('/api/v1/health');
            $this->fail('Expected ApiRateLimitedException.');
        } catch (ApiRateLimitedException $e) {
            $this->assertNull($e->retryAfterSeconds);
        }
    }

    public function test_5xx_maps_to_server_exception(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Server error.'], 500)]);

        try {
            $this->client()->get('/api/v1/health');
            $this->fail('Expected ApiServerException.');
        } catch (ApiServerException $e) {
            $this->assertSame(500, $e->statusCode);
        }
    }

    public function test_a_non_json_body_maps_to_unexpected_response(): void
    {
        Http::fake(['*' => Http::response('<html>Bad Gateway</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->expectException(ApiUnexpectedResponseException::class);

        $this->client()->get('/api/v1/health');
    }

    public function test_an_unmapped_error_status_maps_to_unexpected_response(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Teapot.'], 418)]);

        $this->expectException(ApiUnexpectedResponseException::class);

        $this->client()->get('/api/v1/health');
    }

    // --- Retry: "retry محدود فقط للطلبات الآمنة" --------------------------

    public function test_a_get_request_retries_a_connection_failure_a_limited_number_of_times(): void
    {
        // config('api.restaurant_backend.retry.times') defaults to 2 —
        // Laravel's retry() counts that as 2 *total* attempts (1 initial +
        // 1 retry), not 2 retries on top of the first.
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('timed out');
            }

            return Http::response(['success' => true, 'message' => '', 'data' => []], 200);
        });

        $result = $this->client()->get('/api/v1/health');

        $this->assertTrue($result->success);
        $this->assertSame(2, $attempts);
    }

    public function test_a_post_request_is_never_retried_on_a_connection_failure(): void
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('timed out');
        });

        try {
            $this->client()->post('/api/v1/orders', ['x' => 1]);
            $this->fail('Expected ApiTimeoutException.');
        } catch (ApiTimeoutException) {
            $this->assertSame(1, $attempts);
        }
    }

    public function test_retry_never_converts_a_4xx_response_into_an_exception_that_bypasses_status_handling(): void
    {
        // A regression guard: PendingRequest::retry()'s own default
        // ($throw = true) would otherwise turn this into a generic
        // RequestException instead of the specific ApiValidationException.
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            return Http::response(['success' => false, 'message' => 'Invalid.', 'errors' => []], 422);
        });

        try {
            $this->client()->get('/api/v1/health');
            $this->fail('Expected ApiValidationException.');
        } catch (ApiValidationException) {
            $this->assertSame(1, $attempts, 'A 4xx response must never be retried.');
        }
    }

    // --- Auth token attachment -------------------------------------------

    public function test_the_stored_auth_token_is_attached_as_a_bearer_header(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'message' => '', 'data' => []], 200)]);

        app(AuthTokenStore::class)->put('my-secret-token');

        $this->client()->get('/api/v1/orders');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer my-secret-token'));
    }

    public function test_no_authorization_header_is_sent_when_there_is_no_stored_token(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'message' => '', 'data' => []], 200)]);

        $this->client()->get('/api/v1/health');

        Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
    }

    // --- HTTPS in production ---------------------------------------------

    public function test_it_refuses_a_non_https_base_url_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);

        new ApiClient(
            baseUrl: 'http://not-secure.example.com',
            timeout: 15,
            retryTimes: 0,
            retryDelayMs: 0,
            networkStatus: app(NetworkStatusStore::class),
            authTokenStore: app(AuthTokenStore::class),
        );
    }

    public function test_it_allows_an_https_base_url_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $client = new ApiClient(
            baseUrl: 'https://secure.example.com',
            timeout: 15,
            retryTimes: 0,
            retryDelayMs: 0,
            networkStatus: app(NetworkStatusStore::class),
            authTokenStore: app(AuthTokenStore::class),
        );

        $this->assertInstanceOf(ApiClient::class, $client);
    }

    public function test_a_non_https_base_url_is_allowed_outside_production(): void
    {
        $client = new ApiClient(
            baseUrl: 'http://localhost:8000',
            timeout: 15,
            retryTimes: 0,
            retryDelayMs: 0,
            networkStatus: app(NetworkStatusStore::class),
            authTokenStore: app(AuthTokenStore::class),
        );

        $this->assertInstanceOf(ApiClient::class, $client);
    }
}
