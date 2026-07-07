<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['backend.auth'])->group(function (): void {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile');
    Route::livewire('settings/change-password', 'pages::settings.change-password')->name('change-password');
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');
});
