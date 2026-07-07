<?php

use App\Actions\Api\ResetPasswordAction;
use App\Concerns\HandlesApiExceptions;
use App\Exceptions\Api\ApiException;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Manual token entry, not a deep link — see the Forgot Password screen's
 * docblock and docs/CUSTOMER_APP_AUTH.md for why. `email` is pre-filled
 * from the `?email=` query string when the customer arrives here right
 * after Forgot Password, but is always still an editable, required field
 * (the token was emailed, so the customer types both from that email
 * regardless).
 */
new #[Title('Reset password')] #[Layout('layouts.auth.simple')] class extends Component
{
    use HandlesApiExceptions;

    public string $email = '';

    public string $token = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $done = false;

    public function mount(): void
    {
        $this->email = (string) request()->query('email', '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function resetPassword(ResetPasswordAction $action): void
    {
        $this->generalError = null;
        $validated = $this->validate();

        try {
            $action->execute($validated['email'], $validated['token'], $validated['password'], $this->password_confirmation);
        } catch (ApiException $e) {
            $this->handleApiException($e);

            return;
        }

        $this->done = true;
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('auth.reset_password.title')" :description="__('auth.reset_password.description')" />

    @if ($generalError)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $generalError }}" />
    @endif

    @if ($done)
        <flux:callout variant="success" icon="check-circle" heading="{{ __('auth.reset_password.success') }}" />

        <flux:button :href="route('login')" variant="primary" class="w-full" wire:navigate>
            {{ __('auth.login.title') }}
        </flux:button>
    @else
        <form wire:submit="resetPassword" class="flex flex-col gap-6">
            <flux:input wire:model="email" :label="__('auth.reset_password.email')" type="email" required autocomplete="email" />

            <flux:input
                wire:model="token"
                :label="__('auth.reset_password.token')"
                :description="__('auth.reset_password.token_help')"
                type="text"
                required
            />

            <flux:input
                wire:model="password"
                :label="__('auth.reset_password.password')"
                type="password"
                required
                autocomplete="new-password"
                viewable
            />

            <flux:input
                wire:model="password_confirmation"
                :label="__('auth.reset_password.password_confirmation')"
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
                wire:target="resetPassword"
            >
                <span wire:loading.remove wire:target="resetPassword">{{ __('auth.reset_password.submit') }}</span>
                <span wire:loading wire:target="resetPassword">{{ __('Loading...') }}</span>
            </flux:button>
        </form>
    @endif
</div>
