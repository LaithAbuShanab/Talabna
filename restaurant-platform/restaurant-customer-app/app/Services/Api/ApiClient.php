<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Exceptions\Api\ApiConnectionException;
use App\Exceptions\Api\ApiConnectivityException;
use App\Exceptions\Api\ApiOfflineException;
use App\Exceptions\Api\ApiRateLimitedException;
use App\Exceptions\Api\ApiServerException;
use App\Exceptions\Api\ApiTimeoutException;
use App\Exceptions\Api\ApiUnauthorizedException;
use App\Exceptions\Api\ApiUnexpectedResponseException;
use App\Exceptions\Api\ApiValidationException;
use App\Stores\AuthTokenStore;
use App\Stores\NetworkStatusStore;
use App\Support\SafeLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The single seam every call to restaurant-backend goes through. Nothing
 * else in this app should call the `Http` facade directly against the
 * backend — that would bypass the base-URL/timeout/retry/error-mapping/
 * safe-logging rules this class exists to centralize. See
 * docs/CUSTOMER_APP_API_CLIENT.md for the full contract.
 *
 * Every method returns a plain App\Data\Api\ApiResponseData on success, or
 * throws a specific App\Exceptions\Api\ApiException subclass — never a raw
 * Illuminate\Http\Client exception, and never a bare array a caller has to
 * guess the shape of.
 */
final class ApiClient
{
    /** @var list<string> */
    private const array SAFE_METHODS = ['GET', 'HEAD'];

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly int $retryTimes,
        private readonly int $retryDelayMs,
        private readonly NetworkStatusStore $networkStatus,
        private readonly AuthTokenStore $authTokenStore,
    ) {
        $this->guardHttpsInProduction();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): ApiResponseData
    {
        return $this->request('GET', $path, query: $query);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $path, array $data = []): ApiResponseData
    {
        return $this->request('POST', $path, data: $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $path, array $data = []): ApiResponseData
    {
        return $this->request('PUT', $path, data: $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function patch(string $path, array $data = []): ApiResponseData
    {
        return $this->request('PATCH', $path, data: $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function delete(string $path, array $data = []): ApiResponseData
    {
        return $this->request('DELETE', $path, data: $data);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $data
     */
    private function request(string $method, string $path, array $query = [], array $data = []): ApiResponseData
    {
        // "انقطاع الإنترنت": fail fast, before ever touching the network,
        // when the device itself already knows it's offline — see
        // App\Stores\NetworkStatusStore.
        if (! $this->networkStatus->isOnline()) {
            throw new ApiOfflineException('The device is offline.');
        }

        $url = $this->baseUrl.'/'.ltrim($path, '/');
        $requestId = (string) Str::uuid();

        Log::debug('[api] request', [
            'request_id' => $requestId,
            'method' => $method,
            'url' => $url,
            'query' => SafeLog::redact($query),
            'body' => SafeLog::redact($data),
        ]);

        try {
            $response = $this->pendingRequest($method)->send($method, $url, [
                ...($query !== [] ? ['query' => $query] : []),
                ...($data !== [] ? ['json' => $data] : []),
            ]);
        } catch (ConnectionException $e) {
            $connectivityException = $this->mapConnectionException($e);

            Log::warning('[api] connectivity failure', [
                'request_id' => $requestId,
                'method' => $method,
                'url' => $url,
                'exception' => $connectivityException::class,
            ]);

            throw $connectivityException;
        }

        Log::debug('[api] response', [
            'request_id' => $requestId,
            'method' => $method,
            'url' => $url,
            'status' => $response->status(),
        ]);

        return $this->parseResponse($response);
    }

    private function pendingRequest(string $method): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson();

        if ($this->authTokenStore->hasToken()) {
            $request = $request->withToken((string) $this->authTokenStore->token());
        }

        // "retry محدود فقط للطلبات الآمنة": only GET/HEAD are ever retried,
        // and only for a genuine transport-level failure — exactly what
        // ApiTimeoutException/ApiConnectionException are built from.
        // Two things PendingRequest::retry() would otherwise get wrong if
        // called with just ($times, $delay):
        //   1. Its default ($throw = true) turns a 4xx/5xx *response*
        //      into a thrown RequestException, breaking parseResponse()'s
        //      own status-code handling below.
        //   2. Even with throw: false, it *still* retries a bad response
        //      on every attempt but the last (that's a separate check
        //      from $throw) — so a plain `retry($times, $delay, throw:
        //      false)` would retry a 422/500 response too, not just a
        //      real connection failure.
        // The `when` callback fixes both: it's consulted for *every*
        // would-be retry (a bad response or a real exception alike), and
        // only true ConnectionException instances say yes.
        if ($this->isSafeMethod($method) && $this->retryTimes > 0) {
            $request = $request->retry(
                $this->retryTimes,
                $this->retryDelayMs,
                when: fn ($exception) => $exception instanceof ConnectionException,
                throw: false,
            );
        }

        return $request;
    }

    private function isSafeMethod(string $method): bool
    {
        return in_array(strtoupper($method), self::SAFE_METHODS, true);
    }

    private function parseResponse(Response $response): ApiResponseData
    {
        $status = $response->status();
        $json = $response->json();

        if (! is_array($json)) {
            throw new ApiUnexpectedResponseException(
                'The server returned a response that was not valid JSON.',
                $status,
            );
        }

        if ($response->successful()) {
            return ApiResponseData::fromArray($json);
        }

        $message = (string) ($json['message'] ?? 'The request failed.');
        $errorsField = $json['errors'] ?? [];
        $errors = is_array($errorsField) ? $errorsField : [];

        if ($status === 401) {
            // "حذف token عند 401 المؤكد": a *confirmed* 401 — an actual
            // response from the backend saying this token is invalid/
            // expired/missing — is the only thing that ever clears it.
            // Deliberately not done for 403: that means "this token is
            // valid, but not allowed to do *this*" (e.g. viewing another
            // user's resource) — a completely different, unrelated
            // problem that must never log the user out of a still-good
            // session. And never for a connectivity exception either
            // ("عدم حذف الجلسة بسبب timeout عابر") — those are a
            // different exception hierarchy entirely (ApiConnectivityException),
            // thrown before a response is even received, so this line
            // is never reached for them.
            $this->authTokenStore->forget();
        }

        throw match (true) {
            $status === 401, $status === 403 => new ApiUnauthorizedException($message, $status, $json),
            $status === 422 => new ApiValidationException($message, $status, $json, $errors),
            $status === 429 => new ApiRateLimitedException($message, $status, $json, $this->parseRetryAfter($response)),
            $status >= 500 => new ApiServerException($message, $status, $json),
            default => new ApiUnexpectedResponseException($message, $status, $json),
        };
    }

    private function parseRetryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return $header !== '' && is_numeric($header) ? (int) $header : null;
    }

    /**
     * Guzzle only ever throws one exception type
     * (Illuminate\Http\Client\ConnectionException) for both "couldn't
     * connect at all" and "connected but timed out" — there's no separate
     * exception class to catch. Classifying by message substring is a
     * best-effort heuristic, not a guarantee, but it's enough to pick the
     * more specific/accurate of the two user-facing exceptions when
     * possible, falling back to the generic connectivity one otherwise.
     */
    private function mapConnectionException(ConnectionException $e): ApiConnectivityException
    {
        $message = mb_strtolower($e->getMessage());

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return new ApiTimeoutException('The request to the server timed out.');
        }

        return new ApiConnectionException('Could not connect to the server.');
    }

    /**
     * "استخدام HTTPS في production": refuses to even construct with a
     * non-HTTPS base URL once `APP_ENV=production` — a misconfigured
     * `.env` fails loudly at boot instead of silently sending API traffic
     * (including, eventually, the auth token) over plain HTTP.
     */
    private function guardHttpsInProduction(): void
    {
        if (app()->isProduction() && ! Str::startsWith($this->baseUrl, 'https://')) {
            throw new RuntimeException(
                'RESTAURANT_BACKEND_URL must use HTTPS in production (got: '.$this->baseUrl.').',
            );
        }
    }
}
