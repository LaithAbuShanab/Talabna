<?php

declare(strict_types=1);

namespace App\Data\Api;

/**
 * Mirrors restaurant-backend's `UserResource` exactly (its
 * `app/Http/Resources/UserResource.php`) — returned inside
 * {@see AuthResultData} from register/login, and standalone from
 * `GET /api/v1/profile`.
 */
final readonly class AuthUserData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone,
        public string $role,
        public ?string $emailVerifiedAt,
        public ?string $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: (string) $data['name'],
            email: (string) $data['email'],
            phone: $data['phone'] ?? null,
            role: (string) $data['role'],
            emailVerifiedAt: $data['email_verified_at'] ?? null,
            createdAt: $data['created_at'] ?? null,
        );
    }
}
