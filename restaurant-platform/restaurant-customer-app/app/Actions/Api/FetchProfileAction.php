<?php

declare(strict_types=1);

namespace App\Actions\Api;

use App\Data\Api\AuthUserData;
use App\Exceptions\Api\ApiException;
use App\Services\Api\ApiClient;

/**
 * `GET /api/v1/profile` — the authenticated customer's own record. Used
 * by the Profile screen (always fetches fresh rather than trusting any
 * locally cached copy) and by the Splash screen to both restore/confirm
 * an existing session and, if a stored token has actually gone bad,
 * surface that confirmed 401 (App\Services\Api\ApiClient already clears
 * the token when that happens).
 */
final class FetchProfileAction
{
    public function __construct(private readonly ApiClient $client) {}

    /**
     * @throws ApiException
     */
    public function execute(): AuthUserData
    {
        $response = $this->client->get('/api/v1/profile');

        return AuthUserData::fromArray($response->data);
    }
}
