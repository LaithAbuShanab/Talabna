<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Support\NavigationGroup;
use App\Models\RestaurantSetting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            // Both read live from the single RestaurantSetting row (see
            // docs/API_MENU.md "Bilingual content" — this is the same
            // singleton the public menu API's /restaurant endpoint
            // serves), so editing them on the new Settings page updates
            // the panel's own branding immediately, with no redeploy.
            ->brandName(fn () => RestaurantSetting::current()->restaurant_name)
            ->brandLogo(fn () => ($path = RestaurantSetting::current()->logo_path) !== null ? asset($path) : null)
            ->favicon(fn () => ($path = RestaurantSetting::current()->logo_path) !== null ? asset($path) : null)
            // Filament resolves the whole group list (label + icon per
            // case) straight from this enum — see its class docblock for
            // why some groups have no Resource yet.
            ->navigationGroups(NavigationGroup::class)
            // Reads Illuminate\Notifications\Notifiable's standard
            // ->notifications() relationship, already used by App\Models\User
            // — see the new `notifications` table migration.
            ->databaseNotifications()
            // isSimple: false renders the built-in EditProfile page (name/
            // email/password/current-password, all built into Filament v5
            // core — see vendor/filament/filament/src/Auth/Pages/EditProfile.php)
            // inside the normal panel layout rather than a bare card.
            ->profile(isSimple: false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
