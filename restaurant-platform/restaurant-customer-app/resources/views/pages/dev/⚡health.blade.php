<?php

use App\Actions\Api\CheckApiHealthAction;
use App\Exceptions\Api\ApiException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "health check development screen" — confirms
 * `config('api.restaurant_backend.base_url')` is actually reachable,
 * without digging through logs. Only registered as a route in the
 * `local` environment — see `routes/web.php`.
 *
 * Deliberately stores plain scalars, not the
 * App\Data\Api\HealthCheckData DTO itself, as public properties: Livewire
 * has to serialize ("dehydrate") every public property between requests,
 * and only supports a fixed set of types (scalars, arrays, Enums, Models,
 * Collections, DateTime, ...) — an arbitrary readonly DTO isn't one of
 * them without registering a custom synth, which isn't worth it for a
 * dev-only screen.
 */
new #[Title('API Health Check')] #[Layout('layouts.auth.simple')] class extends Component
{
    public ?string $status = null;

    public ?string $timestamp = null;

    public ?float $responseTimeMs = null;

    public ?string $errorType = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->check();
    }

    public function check(): void
    {
        $this->status = null;
        $this->timestamp = null;
        $this->responseTimeMs = null;
        $this->errorType = null;
        $this->errorMessage = null;

        try {
            $result = app(CheckApiHealthAction::class)->execute();
            $this->status = $result->status;
            $this->timestamp = $result->timestamp;
            $this->responseTimeMs = $result->responseTimeMs;
        } catch (ApiException $e) {
            $this->errorType = $e::class;
            $this->errorMessage = $e->getMessage();
        }
    }
}; ?>

<div class="flex flex-col w-full gap-4">
    <flux:heading size="lg">{{ __('API Health Check') }}</flux:heading>

    <flux:text class="text-zinc-500">
        {{ __('Development only — checks connectivity to :url', ['url' => config('api.restaurant_backend.base_url')]) }}
    </flux:text>

    @if ($status !== null)
        <flux:callout variant="success" icon="check-circle" heading="{{ __('Backend is reachable') }}">
            <flux:callout.text>
                {{ __('Status') }}: {{ $status }}<br>
                {{ __('Server time') }}: {{ $timestamp }}<br>
                {{ __('Response time') }}: {{ $responseTimeMs }} ms
            </flux:callout.text>
        </flux:callout>
    @elseif ($errorMessage !== null)
        <flux:callout variant="danger" icon="x-circle" heading="{{ __('Backend is not reachable') }}">
            <flux:callout.text>
                {{ __('Type') }}: {{ class_basename($errorType) }}<br>
                {{ $errorMessage }}
            </flux:callout.text>
        </flux:callout>
    @endif

    <flux:button variant="primary" wire:click="check" class="self-start">
        {{ __('Check again') }}
    </flux:button>
</div>
