<?php

use App\Actions\Api\ForgotPasswordAction;
use App\Concerns\HandlesApiExceptions;
use App\Exceptions\Api\ApiException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Reset password إن كان deep link مدعومًا، وإلا وثّق المسار المناسب" —
 * no deep link is configured (`NATIVEPHP_DEEPLINK_*` unset) and
 * restaurant-backend itself emails the raw reset **token**, not a link
 * (see docs/API_AUTH.md and docs/CUSTOMER_APP_AUTH.md) — so this screen's
 * only job is to collect the email and tell the customer to check it;
 * they then manually enter the token on the Reset Password screen.
 */
new #[Title('Forgot password')] #[Layout('layouts.auth.simple')] class extends Component
{
    use HandlesApiExceptions;

    public string $email = '';

    public bool $sent = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }

    public function sendResetCode(ForgotPasswordAction $action): void
    {
        $this->generalError = null;
        $validated = $this->validate();

        try {
            $action->execute($validated['email']);
        } catch (ApiException $e) {
            $this->handleApiException($e);

            return;
        }

        // Always the same outcome regardless of whether the account
        // exists — restaurant-backend's own anti-enumeration guarantee
        // (docs/API_AUTH.md), preserved here rather than only at the API
        // layer.
        $this->sent = true;
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('auth.forgot_password.title')" :description="__('auth.forgot_password.description')" />

    @if ($generalError)
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $generalError }}" />
    @endif

    @if ($sent)
        <flux:callout variant="success" icon="check-circle" heading="{{ __('auth.forgot_password.sent') }}" />

        <flux:button :href="route('reset-password')" variant="primary" class="w-full" wire:navigate>
            {{ __('auth.forgot_password.have_code') }}
        </flux:button>
    @else
        <form wire:submit="sendResetCode" class="flex flex-col gap-6">
            <flux:input wire:model="email" :label="__('auth.forgot_password.email')" type="email" required autofocus autocomplete="email" />

            <flux:button
                type="submit"
                variant="primary"
                class="w-full"
                wire:loading.attr="disabled"
                wire:target="sendResetCode"
            >
                <span wire:loading.remove wire:target="sendResetCode">{{ __('auth.forgot_password.submit') }}</span>
                <span wire:loading wire:target="sendResetCode">{{ __('Loading...') }}</span>
            </flux:button>
        </form>
    @endif

    <div class="text-sm text-center text-zinc-600 dark:text-zinc-400">
        <flux:link :href="route('login')" wire:navigate>{{ __('auth.forgot_password.back_to_login') }}</flux:link>
    </div>
</div>
