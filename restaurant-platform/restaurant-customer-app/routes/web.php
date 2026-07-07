<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The app's boot screen — see config/nativephp.php's start_url (default
// '/') and docs/CUSTOMER_APP_AUTH.md. Decides Onboarding vs. Login vs.
// Dashboard on every visit, restoring/confirming an existing session.
Route::livewire('/', 'pages::splash')->name('home');

Route::livewire('onboarding', 'pages::onboarding')->name('onboarding');

// Public — restaurant-backend's own auth endpoints are all unauthenticated
// (see docs/API_AUTH.md).
Route::livewire('login', 'pages::auth.login')->name('login');
Route::livewire('register', 'pages::auth.register')->name('register');
Route::livewire('forgot-password', 'pages::auth.forgot-password')->name('forgot-password');
Route::livewire('reset-password', 'pages::auth.reset-password')->name('reset-password');

// Requires a stored token — see App\Http\Middleware\EnsureBackendSessionExists.
// The actual per-request auth check happens against restaurant-backend
// itself (App\Services\Api\ApiClient); this middleware only stops an
// obviously-signed-out device from even rendering these pages.
Route::middleware('backend.auth')->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('logout', 'pages::logout')->name('logout');
});

// Not behind 'backend.auth': an API failure can happen whether or not the
// user is signed in — see docs/CUSTOMER_APP_API_CLIENT.md.
Route::livewire('error', 'pages::error')->name('error');
Route::livewire('offline', 'pages::offline')->name('offline');

// "health check development screen" — local only, on purpose; this route
// doesn't exist at all outside local development.
if (app()->environment('local')) {
    Route::livewire('dev/health', 'pages::dev.health')->name('dev.health');
}

require __DIR__.'/settings.php';
