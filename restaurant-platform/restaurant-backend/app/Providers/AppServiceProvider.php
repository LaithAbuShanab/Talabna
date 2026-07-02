<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PushNotifier;
use App\Notifications\Push\LogPushNotifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
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
        // Keyed by email+IP (not IP alone) so one abusive IP can't lock out
        // every account behind a shared NAT/proxy, and not email alone so an
        // attacker can't lock a victim out by repeatedly failing their email
        // from anywhere.
        RateLimiter::for('login', function ($request): Limit {
            $key = mb_strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('forgot-password', function ($request): Limit {
            $key = mb_strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(3)->by($key);
        });
    }
}
