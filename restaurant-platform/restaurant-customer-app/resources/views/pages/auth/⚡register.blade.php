<?php

use App\Actions\Api\RegisterAction;
use App\Concerns\HandlesApiExceptions;
use App\Exceptions\Api\ApiException;
use App\Support\DeviceNameResolver;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create an account')] #[Layout('layouts.auth.simple')] class extends Component
{
    use HandlesApiExceptions;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            // Mirrors restaurant-backend's own RegisterRequest rule
            // exactly (see docs/API_AUTH.md) — fast local feedback for
            // the common case; the server call remains the authoritative
            // check (e.g. email uniqueness can't be validated locally).
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function register(RegisterAction $action, DeviceNameResolver $deviceNameResolver): void
    {
        $this->generalError = null;
        $validated = $this->validate();

        try {
            $action->execute(
                $validated['name'],
                $validated['email'],
                $validated['password'],
                $this->password_confirmation,
                $deviceNameResolver->resolve(),
            );
        } catch (ApiException $e) {
            $this->handleApiException($e);

            return;
        }

        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('auth.register.title')" :description="__('auth.register.description')" />

    @if ($generalError)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $generalError }}" />
    @endif

    <form wire:submit="register" class="flex flex-col gap-6">
        <flux:input wire:model="name" :label="__('auth.register.name')" type="text" required autofocus autocomplete="name" />

        <flux:input wire:model="email" :label="__('auth.register.email')" type="email" required autocomplete="email" />

        <flux:input
            wire:model="password"
            :label="__('auth.register.password')"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />

        <flux:input
            wire:model="password_confirmation"
            :label="__('auth.register.password_confirmation')"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />

        <flux:button
            type="submit"
            variant="primary"
            class="w-full"
            wire:loading.attr="disabled"
            wire:target="register"
        >
            <span wire:loading.remove wire:target="register">{{ __('auth.register.submit') }}</span>
            <span wire:loading wire:target="register">{{ __('Loading...') }}</span>
        </flux:button>
    </form>

    <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
        <span>{{ __('auth.register.has_account') }}</span>
        <flux:link :href="route('login')" wire:navigate>{{ __('auth.register.log_in') }}</flux:link>
    </div>
</div>
