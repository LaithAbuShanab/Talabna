<?php

declare(strict_types=1);

namespace App\Actions\Api;

use App\Exceptions\Api\ApiException;
use App\Services\Api\ApiClient;

/**
 * `POST /api/v1/auth/reset-password` — `$token` is the raw value the
 * customer copied out of the forgot-password email (manual entry, no
 * deep link — see docs/CUSTOMER_APP_AUTH.md). On success,
 * restaurant-backend revokes every existing token for the account, so the
 * customer must log in again on every device, including this one — this
 * action does not attempt to log the device in automatically.
 */
final class ResetPasswordAction
{
    public function __construct(private readonly ApiClient $client) {}

    /**
     * @throws ApiException
     */
    public function execute(string $email, string $token, string $password, string $passwordConfirmation): void
    {
        $this->client->post('/api/v1/auth/reset-password', [
            'email' => $email,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ]);
    }
}
