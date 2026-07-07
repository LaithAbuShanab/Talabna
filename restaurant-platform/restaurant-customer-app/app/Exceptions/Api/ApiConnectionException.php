<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * A connectivity failure that isn't specifically a timeout (DNS failure,
 * connection refused, TLS handshake failure, ...) — also the fallback that
 * covers "no internet" when App\Stores\NetworkStatusStore's native check
 * isn't available (e.g. running in a plain browser during development,
 * not inside the compiled native app), since the request itself failing to
 * connect is the only signal available in that context.
 */
final class ApiConnectionException extends ApiConnectivityException {}
