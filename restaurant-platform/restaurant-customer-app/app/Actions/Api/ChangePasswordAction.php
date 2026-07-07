<?php

declare(strict_types=1);

namespace App\Actions\Api;

use App\Exceptions\Api\ApiException;
use App\Services\Api\ApiClient;

/**
 * `PUT /api/v1/profile/password` — restaurant-backend revokes every
 * *other* device's token on success but preserves the one used for this
 * request (see docs/API_AUTH.md), so the current device stays signed in
 * with no further action needed here.
 */
final class ChangePasswordAction
{
    public function __construct(private readonly ApiClient $client) {}

    /**
     * @throws ApiException
     */
    public function execute(string $currentPassword, string $password, string $passwordConfirmation): void
    {
        $this->client->put('/api/v1/profile/password', [
            'current_password' => $currentPassword,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ]);
    }
}
