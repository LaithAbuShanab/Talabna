<?php

declare(strict_types=1);

namespace App\Data\Api;

/**
 * The result of `POST /api/v1/auth/{register,login}` — a user plus the
 * Sanctum bearer token issued for the device that just authenticated. The
 * token is handed to `App\Stores\AuthTokenStore`; it is never logged (see
 * App\Support\SafeLog) and never displayed anywhere.
 */
final readonly class AuthResultData
{
    public function __construct(
        public AuthUserData $user,
        public string $token,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            user: AuthUserData::fromArray($data['user']),
            token: (string) $data['token'],
        );
    }
}
