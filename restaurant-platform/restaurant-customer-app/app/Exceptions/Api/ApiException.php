<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

use Exception;

/**
 * Base type for everything App\Services\Api\ApiClient can throw. Catching
 * this one type is enough for a Livewire component that just wants to show
 * the generic error screen; catch a specific subclass (e.g.
 * ApiOfflineException) when the UI has something better to show (an
 * offline screen, a "please log in again" prompt, field-level validation
 * errors, ...). See docs/CUSTOMER_APP_API_CLIENT.md for the full list and
 * which screen each one is expected to map to.
 */
abstract class ApiException extends Exception {}
