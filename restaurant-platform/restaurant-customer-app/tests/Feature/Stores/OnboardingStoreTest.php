<?php

declare(strict_types=1);

namespace Tests\Feature\Stores;

use App\Stores\OnboardingStore;
use Tests\TestCase;

class OnboardingStoreTest extends TestCase
{
    public function test_onboarding_is_not_completed_by_default(): void
    {
        $this->assertFalse(app(OnboardingStore::class)->hasCompletedOnboarding());
    }

    public function test_marking_completed_persists(): void
    {
        $store = app(OnboardingStore::class);
        $store->markCompleted();

        $this->assertTrue($store->hasCompletedOnboarding());
    }
}
