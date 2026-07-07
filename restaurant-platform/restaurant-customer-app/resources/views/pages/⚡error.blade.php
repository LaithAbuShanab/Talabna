<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "شاشة خطأ عامة" — where a Livewire component redirects when it catches
 * an App\Exceptions\Api\ApiException it has nothing more specific to show
 * for (ApiUnauthorizedException/ApiValidationException/connectivity
 * exceptions all have their own, more helpful handling — see
 * docs/CUSTOMER_APP_API_CLIENT.md). Deliberately generic and static: never
 * displays a raw exception message, matching restaurant-backend's own
 * "no internal details leak to clients" rule (docs/SECURITY.md) applied
 * here on the client side too.
 */
new #[Title('Something went wrong')] #[Layout('layouts.auth.simple')] class extends Component
{
    public function retry(): void
    {
        $this->redirect(route('home'), navigate: true);
    }
}; ?>

<div class="flex flex-col items-center gap-4 text-center">
    <flux:icon.exclamation-triangle class="text-red-500 size-12" />

    <flux:heading size="lg">{{ __('Something went wrong') }}</flux:heading>

    <flux:text class="text-zinc-500">
        {{ __('Please try again in a moment. If the problem continues, contact support.') }}
    </flux:text>

    <flux:button variant="primary" wire:click="retry">
        {{ __('Go back home') }}
    </flux:button>
</div>
