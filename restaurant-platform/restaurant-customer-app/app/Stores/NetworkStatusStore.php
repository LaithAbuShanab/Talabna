<?php

declare(strict_types=1);

namespace App\Stores;

use Native\Mobile\Facades\Network;

/**
 * "انقطاع الإنترنت" — App\Services\Api\ApiClient asks this Store *before*
 * attempting a request, so a device already known to be offline fails
 * fast with App\Exceptions\Api\ApiOfflineException instead of waiting out
 * a timeout for a request that was never going to succeed. Also used
 * directly by the offline screen (`routes/web.php`'s `offline` route) to
 * detect reconnection.
 *
 * Backed by `Native\Mobile\Facades\Network::status()`, which returns
 * `null` (or an object with no `connected` property) whenever there's no
 * real device/bridge behind it — including local web development
 * (`php artisan serve`, which this Livewire app still supports) and this
 * test suite. That's treated as "unknown, assume online" and left for the
 * actual HTTP call to find out — see
 * App\Exceptions\Api\ApiConnectionException, which exists specifically to
 * cover "we assumed online and were wrong".
 */
final class NetworkStatusStore
{
    public function isOnline(): bool
    {
        $status = Network::status();

        if ($status === null || ! property_exists($status, 'connected')) {
            return true;
        }

        return (bool) $status->connected;
    }
}
