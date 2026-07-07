<?php

declare(strict_types=1);

namespace App\Stores;

use App\Support\SecureStorage;

/**
 * Whether this device has already seen the (simple, one-screen) onboarding
 * flow — checked by the Splash screen to decide "first launch ever" vs.
 * "returning device with no session". Not sensitive data, but reuses
 * App\Support\SecureStorage anyway: it's already the one persistent
 * key/value store this app has that works consistently in both the real
 * native app and local browser development, so there's no reason to build
 * a second, parallel storage mechanism just for one boolean flag.
 */
final class OnboardingStore
{
    private const string KEY = 'onboarding_completed';

    public function __construct(private readonly SecureStorage $storage) {}

    public function hasCompletedOnboarding(): bool
    {
        return $this->storage->get(self::KEY) === '1';
    }

    public function markCompleted(): void
    {
        $this->storage->set(self::KEY, '1');
    }
}
