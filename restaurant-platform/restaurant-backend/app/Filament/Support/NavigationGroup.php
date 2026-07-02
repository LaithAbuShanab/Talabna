<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Support\Contracts\HasLabel;

/**
 * The panel's fixed navigation-group taxonomy, passed directly to
 * Panel::navigationGroups() in App\Providers\Filament\AdminPanelProvider —
 * Filament v5 natively supports resolving a whole group list from an enum
 * class-string (see Filament\Navigation\NavigationGroup::fromEnum()), so
 * no manual NavigationGroup::make() calls are needed. A Resource/Page
 * joins a group by setting its own `$navigationGroup` to one of these
 * cases, e.g. `protected static string|UnitEnum|null $navigationGroup =
 * NavigationGroup::Administration;`.
 *
 * Deliberately does *not* implement Filament\Support\Contracts\HasIcon:
 * Filament refuses to render a group that has its own icon if any of its
 * items also have one ("Either the group or its items can have icons, but
 * not both") — and every Resource/Page here sets its own specific
 * `$navigationIcon`, which is more useful than one shared group icon once
 * a group holds more than one item (e.g. Administration: Users vs.
 * Activity Log).
 *
 * Several groups (Orders, Menu, Customers, Promotions, Delivery, Reports)
 * have no Resource assigned yet — see docs/ADMIN_PANEL.md for what this
 * task scoped in vs. deferred. Filament simply omits an empty group from
 * the sidebar, so declaring them now costs nothing and means future
 * Resources only need to reference the matching case.
 */
enum NavigationGroup implements HasLabel
{
    case Dashboard;
    case Orders;
    case Menu;
    case Customers;
    case Promotions;
    case Delivery;
    case Reports;
    case Settings;
    case Administration;

    public function getLabel(): string
    {
        return match ($this) {
            self::Dashboard => __('nav.dashboard'),
            self::Orders => __('nav.orders'),
            self::Menu => __('nav.menu'),
            self::Customers => __('nav.customers'),
            self::Promotions => __('nav.promotions'),
            self::Delivery => __('nav.delivery'),
            self::Reports => __('nav.reports'),
            self::Settings => __('nav.settings'),
            self::Administration => __('nav.administration'),
        };
    }
}
