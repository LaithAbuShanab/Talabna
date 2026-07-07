<?php

use App\Actions\Api\FetchProfileAction;
use App\Exceptions\Api\ApiConnectivityException;
use App\Exceptions\Api\ApiException;
use App\Exceptions\Api\ApiUnauthorizedException;
use App\Stores\AuthTokenStore;
use App\Stores\OnboardingStore;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The app's boot screen (`/` — see `routes/web.php` and
 * `config/nativephp.php`'s `start_url`). "استعادة جلسة المستخدم عند فتح
 * التطبيق": if a token is already stored, this confirms it's still good
 * by calling the one endpoint that requires it — `GET /api/v1/profile` —
 * rather than just trusting the token is still valid.
 *
 * - No onboarding seen yet → Onboarding.
 * - No token → Login.
 * - Token confirmed valid (profile fetch succeeds) → Dashboard.
 * - Token confirmed **invalid** (a real 401 — App\Services\Api\ApiClient
 *   already cleared it) → Login.
 * - Couldn't confirm either way (offline/timeout/connection/server error)
 *   → "عدم حذف الجلسة بسبب timeout عابر": the token is left alone and the
 *   app proceeds to Dashboard optimistically. A real API call made from
 *   Dashboard onward will surface the same connectivity problem again if
 *   it's still there, without having logged the customer out over a
 *   transient blip.
 */
new #[Title('Loading')] #[Layout('layouts.auth.simple')] class extends Component
{
    public function mount(OnboardingStore $onboarding, AuthTokenStore $tokenStore, FetchProfileAction $fetchProfile): void
    {
        if (! $onboarding->hasCompletedOnboarding()) {
            $this->redirect(route('onboarding'), navigate: true);

            return;
        }

        if (! $tokenStore->hasToken()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        try {
            $fetchProfile->execute();
            $this->redirect(route('dashboard'), navigate: true);
        } catch (ApiUnauthorizedException) {
            $this->redirect(route('login'), navigate: true);
        } catch (ApiConnectivityException) {
            $this->redirect(route('dashboard'), navigate: true);
        } catch (ApiException) {
            $this->redirect(route('error'), navigate: true);
        }
    }
}; ?>

<div class="flex flex-col items-center gap-4 text-center">
    <x-app-logo-icon class="size-16 text-black dark:text-white" />
    <flux:heading size="lg">{{ config('app.name') }}</flux:heading>
</div>
