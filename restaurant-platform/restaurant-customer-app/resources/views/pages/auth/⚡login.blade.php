<?php

use App\Actions\Api\LoginAction;
use App\Concerns\HandlesApiExceptions;
use App\Exceptions\Api\ApiException;
use App\Support\DeviceNameResolver;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Log in')] #[Layout('layouts.auth.simple')] class extends Component
{
    use HandlesApiExceptions;

    public string $email = '';

    public string $password = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function login(LoginAction $action, DeviceNameResolver $deviceNameResolver): void
    {
        $this->generalError = null;
        $validated = $this->validate();

        try {
            $action->execute($validated['email'], $validated['password'], $deviceNameResolver->resolve());
        } catch (ApiException $e) {
            $this->handleApiException($e);

            return;
        }

        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('auth.login.title')" :description="__('auth.login.description')" />

    @if ($generalError)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $generalError }}" />
    @endif

    <form wire:submit="login" class="flex flex-col gap-6">
        <flux:input
            wire:model="email"
            :label="__('auth.login.email')"
            type="email"
            required
            autofocus
            autocomplete="email"
        />

        <flux:input
            wire:model="password"
            :label="__('auth.login.password')"
            type="password"
            required
            autocomplete="current-password"
            viewable
        />

        <div class="flex items-center justify-end">
            <flux:link :href="route('forgot-password')" wire:navigate>
                {{ __('auth.login.forgot_password') }}
            </flux:link>
        </div>

        <flux:button
            type="submit"
            variant="primary"
            class="w-full"
            wire:loading.attr="disabled"
            wire:target="login"
        >
            <span wire:loading.remove wire:target="login">{{ __('auth.login.submit') }}</span>
            <span wire:loading wire:target="login">{{ __('Loading...') }}</span>
        </flux:button>
    </form>

    <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
        <span>{{ __('auth.login.no_account') }}</span>
        <flux:link :href="route('register')" wire:navigate>{{ __('auth.login.sign_up') }}</flux:link>
    </div>
</div>
