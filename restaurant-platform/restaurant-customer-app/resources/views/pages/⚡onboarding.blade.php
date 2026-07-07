<?php

use App\Stores\OnboardingStore;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Onboarding بسيط" — a single screen, on purpose: one heading, one line
 * of description, one button. Marks itself seen (App\Stores\OnboardingStore)
 * so the Splash screen never shows it again on this device.
 */
new #[Title('Welcome')] #[Layout('layouts.auth.simple')] class extends Component
{
    public function getStarted(OnboardingStore $onboarding): void
    {
        $onboarding->markCompleted();

        $this->redirect(route('login'), navigate: true);
    }
}; ?>

<div class="flex flex-col items-center gap-6 text-center">
    <x-app-logo-icon class="size-16 text-black dark:text-white" />

    <x-auth-header :title="__('auth.onboarding.title')" :description="__('auth.onboarding.description')" />

    <flux:button variant="primary" wire:click="getStarted" class="w-full">
        {{ __('auth.onboarding.get_started') }}
    </flux:button>
</div>
