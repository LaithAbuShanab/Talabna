<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Exceptions\Api\ApiConnectivityException;
use App\Exceptions\Api\ApiException;
use App\Exceptions\Api\ApiRateLimitedException;
use App\Exceptions\Api\ApiValidationException;

/**
 * Shared by every form Livewire component that calls an
 * App\Actions\Api\* action directly (Login, Register, Forgot/Reset
 * password, Profile, Change password) — the same "422 becomes per-field
 * errors, anything else becomes one banner message" shape applies to all
 * of them identically, so it lives here once rather than being retyped
 * six times.
 *
 * "عرض أخطاء 422 القادمة من الخادم": ApiValidationException::$errors is
 * rendered through Livewire's own `$errors` bag via `addError()`, so
 * `@error('field')` in the Blade template works exactly the same for a
 * server-side 422 as it does for `$this->validate()`'s own local errors —
 * no separate error-display code path needed for either.
 */
trait HandlesApiExceptions
{
    public ?string $generalError = null;

    protected function handleApiException(ApiException $e): void
    {
        $this->generalError = null;

        if ($e instanceof ApiValidationException) {
            foreach ($e->errors as $field => $messages) {
                $text = is_array($messages) ? implode(' ', array_map('strval', $messages)) : (string) $messages;
                $this->addError((string) $field, $text);
            }

            return;
        }

        $this->generalError = match (true) {
            $e instanceof ApiRateLimitedException => __('Too many attempts. Please try again shortly.'),
            $e instanceof ApiConnectivityException => __('Connection problem. Please check your internet and try again.'),
            default => __('Something went wrong. Please try again.'),
        };
    }
}
