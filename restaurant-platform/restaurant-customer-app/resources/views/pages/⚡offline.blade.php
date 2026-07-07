<?php

use App\Stores\NetworkStatusStore;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "شاشة offline" — where App\Exceptions\Api\ApiConnectivityException
 * (offline/timeout/connection-failure) is expected to redirect. Polls
 * App\Stores\NetworkStatusStore every 5 seconds and bounces back home
 * automatically the moment connectivity returns, with a manual "try
 * again" button for anyone who doesn't want to wait for the next poll.
 */
new #[Title('You are offline')] #[Layout('layouts.auth.simple')] class extends Component
{
    public function mount(): void
    {
        $this->checkConnection();
    }

    public function checkConnection(): void
    {
        if (app(NetworkStatusStore::class)->isOnline()) {
            $this->redirect(route('home'), navigate: true);
        }
    }
}; ?>

<div wire:poll.5s="checkConnection" class="flex flex-col items-center gap-4 text-center">
    <flux:icon.signal-slash class="text-zinc-400 size-12" />

    <flux:heading size="lg">{{ __('You are offline') }}</flux:heading>

    <flux:text class="text-zinc-500">
        {{ __('Check your internet connection — we\'ll reconnect automatically once it\'s back.') }}
    </flux:text>

    <flux:button variant="primary" wire:click="checkConnection">
        {{ __('Try again') }}
    </flux:button>
</div>
