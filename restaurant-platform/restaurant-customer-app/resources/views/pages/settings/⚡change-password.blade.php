<?php

use App\Actions\Api\ChangePasswordAction;
use App\Concerns\HandlesApiExceptions;
use App\Exceptions\Api\ApiException;
use App\Exceptions\Api\ApiUnauthorizedException;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Change password')] class extends Component
{
    use HandlesApiExceptions;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $saved = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function save(ChangePasswordAction $action): void
    {
        $this->generalError = null;
        $this->saved = false;
        $validated = $this->validate();

        try {
            $action->execute($validated['current_password'], $validated['password'], $this->password_confirmation);
        } catch (ApiUnauthorizedException) {
            $this->redirect(route('login'), navigate: true);

            return;
        } catch (ApiException $e) {
            $this->handleApiException($e);

            return;
        }

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->saved = true;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('auth.change_password.title') }}</flux:heading>

    <x-pages::settings.layout :heading="__('auth.change_password.title')" :subheading="__('auth.change_password.title')">
        @if ($generalError)
            <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $generalError }}" class="mb-6" />
        @endif

        @if ($saved)
            <flux:callout variant="success" icon="check-circle" heading="{{ __('auth.change_password.saved') }}" class="mb-6" />
        @endif

        <form wire:submit="save" class="my-6 w-full space-y-6">
            <flux:input
                wire:model="current_password"
                :label="__('auth.change_password.current_password')"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />

            <flux:input
                wire:model="password"
                :label="__('auth.change_password.password')"
                type="password"
                required
                autocomplete="new-password"
                viewable
            />

            <flux:input
                wire:model="password_confirmation"
                :label="__('auth.change_password.password_confirmation')"
                type="password"
                required
                autocomplete="new-password"
                viewable
            />

            <flux:button
                type="submit"
                variant="primary"
                wire:loading.attr="disabled"
                wire:target="save"
            >
                <span wire:loading.remove wire:target="save">{{ __('auth.change_password.submit') }}</span>
                <span wire:loading wire:target="save">{{ __('Loading...') }}</span>
            </flux:button>
        </form>
    </x-pages::settings.layout>
</section>
