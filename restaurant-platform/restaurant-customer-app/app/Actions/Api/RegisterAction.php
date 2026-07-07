<?php

declare(strict_types=1);

namespace App\Actions\Api;

use App\Data\Api\AuthResultData;
use App\Exceptions\Api\ApiException;
use App\Services\Api\ApiClient;
use App\Stores\AuthTokenStore;

/**
 * `POST /api/v1/auth/register` — creates a new customer account and
 * immediately stores the issued token, so a successful register leaves
 * the device already signed in (matches restaurant-backend's own
 * behavior — see docs/API_AUTH.md).
 */
final class RegisterAction
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly AuthTokenStore $authTokenStore,
    ) {}

    /**
     * @throws ApiException
     */
    public function execute(
        string $name,
        string $email,
        string $password,
        string $passwordConfirmation,
        string $deviceName,
    ): AuthResultData {
        $response = $this->client->post('/api/v1/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
            'device_name' => $deviceName,
        ]);

        $result = AuthResultData::fromArray($response->data);

        $this->authTokenStore->put($result->token);

        return $result;
    }
}
