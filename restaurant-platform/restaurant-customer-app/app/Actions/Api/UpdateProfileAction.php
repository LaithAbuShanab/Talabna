<?php

declare(strict_types=1);

namespace App\Actions\Api;

use App\Data\Api\AuthUserData;
use App\Exceptions\Api\ApiException;
use App\Services\Api\ApiClient;

/**
 * `PUT /api/v1/profile` — partial update; only the fields actually passed
 * are sent (restaurant-backend's `UpdateProfileRequest` marks every field
 * `sometimes`), so a caller can update just the name, just the email, etc.
 */
final class UpdateProfileAction
{
    public function __construct(private readonly ApiClient $client) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function execute(array $data): AuthUserData
    {
        $response = $this->client->put('/api/v1/profile', $data);

        return AuthUserData::fromArray($response->data);
    }
}
