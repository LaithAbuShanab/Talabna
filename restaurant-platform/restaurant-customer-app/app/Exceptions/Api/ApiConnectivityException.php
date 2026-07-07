<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

/**
 * No HTTP response was received at all — the device has no network path to
 * restaurant-backend, for one of a few reasons (see the concrete
 * subclasses). This is the category the "offline" screen is shown for
 * (`routes/web.php`'s `offline` route) — as opposed to ApiHttpException,
 * where the backend *did* respond, just with an error.
 */
abstract class ApiConnectivityException extends ApiException {}
