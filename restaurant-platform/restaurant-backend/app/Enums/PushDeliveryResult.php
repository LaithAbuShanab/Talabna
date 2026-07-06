<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a concrete App\Contracts\PushNotifier implementation reports back per
 * device token, after actually attempting delivery. `InvalidToken` is a
 * normal, expected outcome (the provider telling us the token is dead) —
 * App\Jobs\SendCustomerPushNotificationJob deactivates the token for it, it
 * never throws. A genuinely transient failure (provider unreachable, rate
 * limited, ...) is a thrown exception instead, so the job's own retry/
 * backoff can take over — see that class's docblock.
 */
enum PushDeliveryResult: string
{
    case Sent = 'sent';
    case InvalidToken = 'invalid_token';
}
