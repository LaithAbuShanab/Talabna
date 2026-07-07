<?php

use App\Actions\Api\FetchProfileAction;
use App\Actions\Api\UpdateProfileAction;
use App\Concerns\HandlesApiExceptions;
use App\Exceptions\Api\ApiException;
use App\Exceptions\Api\ApiUnauthorizedException;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Always fetches fresh from `GET /api/v1/profile` on mount rather than
 * trusting any locally cached copy — see docs/CUSTOMER_APP_AUTH.md.
 */
new #[Title('Profile settings')] class extends Component
{
    use HandlesApiExceptions;

    public string $name = '';

    public string $email = '';

    public ?string $phone = null;

    public bool $saved = false;

    public function mount(FetchProfileAction $fetchProfile): void
    {
        try {
            $user = $fetchProfile->execute();
        } catch (ApiUnauthorizedException) {
            $this->redirect(route('login'), navigate: true);

            return;
        } catch (ApiException $e) {
            $this->handleApiException($e);

            return;
        }

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function save(UpdateProfileAction $action): void
    {
        $this->generalError = null;
        $this->saved = false;
        $validated = $this->validate();

        try {
            $user = $action->execute($validated);
        } catch (ApiUnauthorizedException) {
            $this->redirect(route('login'), navigate: true);

            return;
        } catch (ApiException $e) {
            $this->handleApiException($e);

            return;
        }

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->saved = true;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('auth.profile.title') }}</flux:heading>

    <x-pages::settings.layout :heading="__('auth.profile.title')" :subheading="__('auth.profile.title')">
        @if ($generalError)
            <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $generalError }}" class="mb-6" />
        @endif

        @if ($saved)
            <flux:callout variant="success" icon="check-circle" heading="{{ __('auth.profile.saved') }}" class="mb-6" />
        @endif

        <form wire:submit="save" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('auth.profile.name')" type="text" required autofocus autocomplete="name" />

            <flux:input wire:model="email" :label="__('auth.profile.email')" type="email" required autocomplete="email" />

            <flux:input wire:model="phone" :label="__('auth.profile.phone')" type="tel" autocomplete="tel" />

            <flux:button
                type="submit"
                variant="primary"
                wire:loading.attr="disabled"
                wire:target="save"
                data-test="update-profile-button"
            >
                <span wire:loading.remove wire:target="save">{{ __('auth.profile.save') }}</span>
                <span wire:loading wire:target="save">{{ __('Loading...') }}</span>
            </flux:button>
        </form>

        <flux:separator class="my-6" />

        <div class="flex flex-col gap-3 sm:flex-row">
            <flux:button :href="route('change-password')" variant="ghost" wire:navigate>
                {{ __('auth.profile.change_password') }}
            </flux:button>

            <flux:button :href="route('logout')" variant="ghost" wire:navigate>
                {{ __('auth.profile.log_out') }}
            </flux:button>
        </div>
    </x-pages::settings.layout>
</section>
