<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Api\ApiClient;
use App\Stores\AuthTokenStore;
use App\Stores\NetworkStatusStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per request — config/api.php is the only source of
        // truth for base_url/timeout/retry, see docs/CUSTOMER_APP_API_CLIENT.md.
        $this->app->singleton(ApiClient::class, function ($app): ApiClient {
            return new ApiClient(
                baseUrl: (string) config('api.restaurant_backend.base_url'),
                timeout: (int) config('api.restaurant_backend.timeout'),
                retryTimes: (int) config('api.restaurant_backend.retry.times'),
                retryDelayMs: (int) config('api.restaurant_backend.retry.delay_ms'),
                networkStatus: $app->make(NetworkStatusStore::class),
                authTokenStore: $app->make(AuthTokenStore::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
