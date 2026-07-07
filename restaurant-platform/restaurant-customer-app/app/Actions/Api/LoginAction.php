<?php

declare(strict_types=1);

namespace App\Actions\Api;

use App\Data\Api\AuthResultData;
use App\Exceptions\Api\ApiException;
use App\Services\Api\ApiClient;
use App\Stores\AuthTokenStore;

/**
 * `POST /api/v1/auth/login` — restaurant-backend returns the same generic
 * `422` on `email` whether the address or the password was wrong (see
 * docs/API_AUTH.md), so this action never tries to distinguish the two
 * either; that's surfaced as an ordinary ApiValidationException.
 */
final class LoginAction
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly AuthTokenStore $authTokenStore,
    ) {}

    /**
     * @throws ApiException
     */
    public function execute(string $email, string $password, string $deviceName): AuthResultData
    {
        $response = $this->client->post('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => $deviceName,
        ]);

        $result = AuthResultData::fromArray($response->data);

        $this->authTokenStore->put($result->token);

        return $result;
    }
}
