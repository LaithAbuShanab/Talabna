<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * Thrown *before* even attempting the HTTP call, when
 * App\Stores\NetworkStatusStore already knows the device has no
 * connection (native bridge only — see that class). Faster and more
 * accurate than waiting for a request to time out when we already know
 * it's pointless to try.
 */
final class ApiOfflineException extends ApiConnectivityException {}
