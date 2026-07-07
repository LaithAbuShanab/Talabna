<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Restaurant Backend API
    |--------------------------------------------------------------------------
    |
    | This app never connects to a database directly — every piece of data
    | it shows comes from restaurant-backend's REST API, over HTTPS in any
    | non-local environment (App\Services\Api\ApiClient refuses to boot
    | against a non-HTTPS base_url when app()->isProduction()). See
    | docs/CUSTOMER_APP_API_CLIENT.md.
    |
    */

    'restaurant_backend' => [
        'base_url' => env('RESTAURANT_BACKEND_URL', 'http://localhost:8000'),

        'timeout' => (int) env('RESTAURANT_BACKEND_API_TIMEOUT', 15),

        /*
        |----------------------------------------------------------------------
        | Retry
        |----------------------------------------------------------------------
        |
        | Only ever applied to safe (GET/HEAD) requests — see
        | App\Services\Api\ApiClient::isSafeMethod(). A POST/PUT/PATCH/DELETE
        | is never retried automatically: retrying a non-idempotent request
        | on a network hiccup risks silently duplicating a side effect (e.g.
        | placing an order twice), which is a strictly worse failure than
        | just surfacing the error once.
        |
        */
        'retry' => [
            'times' => (int) env('RESTAURANT_BACKEND_API_RETRY_TIMES', 2),
            'delay_ms' => (int) env('RESTAURANT_BACKEND_API_RETRY_DELAY_MS', 200),
        ],
    ],

];
