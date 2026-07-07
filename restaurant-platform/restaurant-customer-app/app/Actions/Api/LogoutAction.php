<?php

declare(strict_types=1);

namespace App\Actions\Api;

use App\Exceptions\Api\ApiException;
use App\Services\Api\ApiClient;
use App\Stores\AuthTokenStore;

/**
 * `POST /api/v1/auth/logout` — revokes only the current device's token
 * server-side (other devices stay logged in). The local token is cleared
 * **unconditionally**, even if the API call itself fails (offline, server
 * error, ...): the customer explicitly asked to log out, and a mobile app
 * that appears to still be logged in after tapping "Log out" is a worse
 * outcome than the server-side token lingering a little longer than
 * intended (it can still be revoked later — e.g. next successful
 * request, or from another device's "log out all devices").
 */
final class LogoutAction
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly AuthTokenStore $authTokenStore,
    ) {}

    public function execute(): void
    {
        try {
            $this->client->post('/api/v1/auth/logout');
        } catch (ApiException) {
            // Best-effort only — see class docblock.
        } finally {
            $this->authTokenStore->forget();
        }
    }
}
