<?php

declare(strict_types=1);

namespace App\Stores;

use App\Support\SecureStorage;

/**
 * The one place the restaurant-backend Sanctum bearer token (issued by its
 * `POST /api/v1/auth/login`) is read, written, or cleared from — every
 * other class asks this Store, never App\Support\SecureStorage directly,
 * so "where does the auth token live" has exactly one answer. Backed by
 * SecureStorage, so the token itself is never in `localStorage`, a plain
 * cookie, or a database row — see that class's docblock for the native
 * Keychain/Keystore vs. dev-fallback story.
 *
 * Not yet wired to an actual login flow (App\Services\Api\ApiClient's
 * consumers don't call restaurant-backend's auth endpoints yet) — this is
 * the storage seam that flow will use once it exists.
 */
final class AuthTokenStore
{
    private const string KEY = 'restaurant_backend_api_token';

    public function __construct(private readonly SecureStorage $storage) {}

    public function token(): ?string
    {
        return $this->storage->get(self::KEY);
    }

    public function hasToken(): bool
    {
        return $this->token() !== null;
    }

    public function put(string $token): void
    {
        $this->storage->set(self::KEY, $token);
    }

    public function forget(): void
    {
        $this->storage->delete(self::KEY);
    }
}
