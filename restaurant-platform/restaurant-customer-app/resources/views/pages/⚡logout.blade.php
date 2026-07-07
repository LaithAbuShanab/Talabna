<?php

use App\Actions\Api\LogoutAction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A confirmation screen rather than an instant action — avoids an
 * accidental tap logging the customer out with no chance to back out.
 * See App\Actions\Api\LogoutAction for why the local token is always
 * cleared regardless of whether the server-side revoke call succeeds.
 */
new #[Title('Log out')] #[Layout('layouts.auth.simple')] class extends Component
{
    public function confirm(LogoutAction $action): void
    {
        $action->execute();

        $this->redirect(route('login'), navigate: true);
    }
}; ?>

<div class="flex flex-col items-center gap-6 text-center">
    <flux:icon.arrow-right-start-on-rectangle class="text-zinc-400 size-12" />

    <x-auth-header :title="__('auth.logout.title')" :description="__('auth.logout.description')" />

    <div class="flex w-full flex-col gap-3">
        <flux:button
            variant="danger"
            wire:click="confirm"
            wire:loading.attr="disabled"
            wire:target="confirm"
            class="w-full"
        >
            <span wire:loading.remove wire:target="confirm">{{ __('auth.logout.confirm') }}</span>
            <span wire:loading wire:target="confirm">{{ __('Loading...') }}</span>
        </flux:button>

        <flux:button :href="route('profile')" variant="ghost" class="w-full" wire:navigate>
            {{ __('auth.logout.cancel') }}
        </flux:button>
    </div>
</div>
