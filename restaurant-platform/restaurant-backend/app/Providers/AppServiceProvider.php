<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PushNotifier;
use App\Notifications\Push\LogPushNotifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // No real push provider is wired up yet — see App\Contracts\PushNotifier
        // and App\Notifications\Push\LogPushNotifier. Replace this binding with
        // a concrete FCM/APNs/NativePHP-push implementation when one exists.
        $this->app->bind(PushNotifier::class, LogPushNotifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
