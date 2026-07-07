<?php

declare(strict_types=1);

namespace App\Actions\Api;

use App\Exceptions\Api\ApiException;
use App\Services\Api\ApiClient;

/**
 * `POST /api/v1/auth/forgot-password` — always succeeds regardless of
 * whether the email belongs to a real account (anti-enumeration — see
 * docs/API_AUTH.md); the caller shows the same generic "check your email"
 * message either way. If an account exists, restaurant-backend emails the
 * **raw reset token** (not a link — see docs/CUSTOMER_APP_AUTH.md "Reset
 * password" for why there's no deep link), which the customer copies into
 * the Reset Password screen.
 */
final class ForgotPasswordAction
{
    public function __construct(private readonly ApiClient $client) {}

    /**
     * @throws ApiException
     */
    public function execute(string $email): void
    {
        $this->client->post('/api/v1/auth/forgot-password', ['email' => $email]);
    }
}
