<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

/**
 * The seam between "something happened, notify a user" and whichever push
 * provider actually sends it (FCM, APNs, NativePHP push, ...). Nothing in
 * this codebase depends on a concrete provider directly — only on this
 * interface — so swapping providers later is a one-line binding change in
 * a service provider, not a change to every call site.
 */
interface PushNotifier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(User $user, string $title, string $body, array $data = []): void;
}
