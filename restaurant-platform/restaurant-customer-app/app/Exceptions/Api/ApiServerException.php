<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * 5xx — restaurant-backend itself failed. Nothing the customer app can fix
 * client-side; maps to the generic error screen. GET requests may have
 * already been retried a limited number of times before this is thrown —
 * see App\Services\Api\ApiClient.
 */
final class ApiServerException extends ApiHttpException {}
