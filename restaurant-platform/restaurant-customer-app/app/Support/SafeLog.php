<?php

declare(strict_types=1);

namespace App\Support;

/**
 * "logging آمن دون tokens أو كلمات مرور" — the one place request/response
 * data is scrubbed before it's ever handed to `Log::`. App\Services\Api\ApiClient
 * routes every log call through this, so no call site has to remember to
 * redact anything itself.
 */
final class SafeLog
{
    /**
     * Exact, case-insensitive key matches. `isSensitiveKey()` also matches
     * anything *ending in* `_token`/`_password`/`_secret`/`_key`, so this
     * list only needs the exact names actually used across this app and
     * restaurant-backend's API, not every possible variation.
     *
     * @var list<string>
     */
    private const array REDACTED_KEYS = [
        'token', 'access_token', 'refresh_token', 'api_token', 'bearer',
        'password', 'password_confirmation', 'current_password',
        'secret', 'api_key', 'apikey', 'client_secret',
        'authorization', 'cookie', 'set-cookie',
    ];

    private const string PLACEHOLDER = '[REDACTED]';

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $result[$key] = match (true) {
                self::isSensitiveKey((string) $key) => self::PLACEHOLDER,
                is_array($value) => self::redact($value),
                default => $value,
            };
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public static function redactHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $name => $value) {
            $result[$name] = self::isSensitiveKey((string) $name) ? self::PLACEHOLDER : $value;
        }

        return $result;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = mb_strtolower($key);

        if (in_array($normalized, self::REDACTED_KEYS, true)) {
            return true;
        }

        foreach (['_token', '_password', '_secret', '_key'] as $suffix) {
            if (str_ends_with($normalized, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
