<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * Catch-all for "we got an HTTP response, but couldn't make sense of it":
 * a body that isn't valid JSON (e.g. an HTML error page from a
 * reverse proxy/CDN in front of the backend), or a success-range status
 * whose body doesn't match the expected `{success, message, data}`
 * envelope. Maps to the generic error screen, same as ApiServerException.
 */
final class ApiUnexpectedResponseException extends ApiHttpException {}
