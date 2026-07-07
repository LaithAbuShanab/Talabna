<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Stores\OnboardingStore;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    public function test_it_renders(): void
    {
        $this->get('/onboarding')->assertOk()->assertSeeText('Welcome');
    }

    public function test_get_started_marks_onboarding_complete_and_redirects_to_login(): void
    {
        Livewire::test('pages::onboarding')
            ->call('getStarted')
            ->assertRedirect(route('login'));

        $this->assertTrue(app(OnboardingStore::class)->hasCompletedOnboarding());
    }
}
