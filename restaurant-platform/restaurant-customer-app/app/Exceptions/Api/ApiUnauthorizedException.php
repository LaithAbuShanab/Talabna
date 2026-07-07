<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * 401 (missing/invalid/expired token) or 403 (authenticated but not
 * allowed). The caller should treat this as "the stored token is no
 * longer good" — clear it via App\Stores\AuthTokenStore and prompt to log
 * in again, rather than showing the generic error screen.
 */
final class ApiUnauthorizedException extends ApiHttpException {}
