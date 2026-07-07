# Architecture

## Overview

This platform is a single-restaurant food ordering system made of two independent
Laravel projects that live side by side in this repository:

| Project | Purpose | Stack |
|---|---|---|
| `restaurant-backend` | Source of truth: database, business logic, REST API, admin panel | Laravel 13, Filament v5, Sanctum |
| `restaurant-customer-app` | Customer-facing mobile app | Laravel 13, Livewire, Tailwind, NativePHP Mobile v3 |

## Core rule: one database, owned by the backend

- The **only** relational database in this system lives inside `restaurant-backend`
  (SQLite in development, MySQL or PostgreSQL in production — see
  `restaurant-backend/config/database.php`).
- `restaurant-customer-app` **never connects to that database directly** and never
  will. It has no database credentials for the backend's database and no Eloquent
  models that map to backend tables.
- All data the customer app needs (menu items, prices, order status, etc.) is
  obtained by calling the backend's REST API over **HTTPS**, authenticated with
  Laravel Sanctum API tokens issued by the backend.
- All prices and totals are calculated and validated **server-side** in
  `restaurant-backend`. The customer app must never be trusted to send a price;
  it only sends item identifiers and quantities, and the backend computes the
  authoritative total.

```
┌─────────────────────────────┐        HTTPS REST API        ┌───────────────────────────────┐
│  restaurant-customer-app     │ ────────────────────────────▶ │  restaurant-backend            │
│  (Laravel + Livewire +       │ ◀──────────────────────────── │  (Laravel + Filament v5 admin  │
│   NativePHP Mobile)          │      Sanctum bearer tokens     │   panel + REST API)             │
│                               │                                │                                 │
│  Local SQLite (own file) —    │                                │  Primary database (SQLite dev / │
│  future caching/offline only,│                                │   MySQL or PostgreSQL prod)     │
│  never the source of truth   │                                │  Filament admin at /admin       │
└─────────────────────────────┘                                └───────────────────────────────┘
```

## `restaurant-backend`

- Laravel 13 application, PHP `^8.3` (dev PHP 8.3 in this environment — see
  `docs/PROJECT_STATE.md` for the PHP 8.4/8.3 note).
- Exposes a versioned-later REST API under `/api/*`, authenticated with
  **Laravel Sanctum** (bearer tokens, not cookie-based SPA auth — the customer
  app is a separate native application, not a same-site SPA).
- Ships the **FilamentPHP v5** admin panel, mounted at `/admin`, panel id `admin`.
  Public self-registration is disabled — admin/staff accounts are provisioned
  manually (`php artisan make:filament-user`) or by another authorized admin,
  never through a public sign-up form.
- Owns the database schema and all business logic (services/actions, Form
  Requests, API Resources, Policies/Gates, Enums for order/payment/delivery
  status, database transactions around order creation and money-affecting
  operations). See `docs/API_CONVENTIONS.md` for API-level conventions.
- Money is stored as integers in the smallest currency unit (e.g. fils/cents),
  never as float.
- All timestamps are stored in UTC and converted to the configured timezone
  only at the presentation layer.

## `restaurant-customer-app`

- Laravel 13 application scaffolded with the official Livewire starter kit
  (Blade + Livewire + Tailwind), then layered with **NativePHP Mobile v3**
  (`nativephp/mobile`) to ship as an Android/iOS app.
- **Authentication is entirely backend-API-based, not local.** The
  starter kit's Laravel Fortify (local session auth, 2FA, passkeys) was
  removed entirely — real customer identity lives only on
  `restaurant-backend`, reached via Sanctum bearer tokens. The app's own
  local `users` table/model exist only because Laravel's framework
  internals expect a default auth provider; no real customer row is ever
  created there. See `docs/CUSTOMER_APP_AUTH.md` for the 9 auth screens
  (Splash, Onboarding, Login, Register, Forgot/Reset password, Logout,
  Profile, Change password), the session-restoration flow, and the
  confirmed-401-only token-clearing rule.
- Has its own local SQLite database file, but that database is for the app's
  own local Laravel needs (sessions, cache, queue) and, later, **on-device
  caching of API responses only** — it is not a copy of, or a replacement for,
  the backend's database, and no restaurant/order domain tables live there.
- Talks to `restaurant-backend` exclusively through its HTTPS REST API,
  via a centralized `App\Services\Api\ApiClient` — base URL/timeout from
  `config/api.php` (`RESTAURANT_BACKEND_URL`/`RESTAURANT_BACKEND_API_TIMEOUT`
  in `.env`), limited retry on safe (GET/HEAD) requests only, typed
  exceptions per failure mode, and safe (token/password-redacted) logging.
  See `docs/CUSTOMER_APP_API_CLIENT.md` for the full architecture.
- Ships with Arabic (`ar`) as the default UI locale and RTL layout support
  (`dir="rtl"` applied automatically based on `app()->getLocale()`), with
  English as the fallback locale.
- Base NativePHP setup is installed (`php artisan native:install`, Android
  project scaffold under `nativephp/`); no additional native device
  *features* (camera, push notifications, biometrics, etc.) have been
  wired into the app's own UI yet — those remain separate, explicitly-scoped
  tasks. The package does already ship `Native\Mobile\SecureStorage`
  (Keychain/Keystore) and `Native\Mobile\Network` (connectivity status)
  as part of the base install, and this app's `App\Support\SecureStorage`/
  `App\Stores\NetworkStatusStore` already use both.

## Environments and configuration

- Development: both apps use SQLite (`database/database.sqlite`), file-based
  cache/queue/session by default.
- Production: `restaurant-backend` is expected to run against MySQL or
  PostgreSQL (`DB_CONNECTION=mysql` or `pgsql` in `.env`, see
  `restaurant-backend/.env.example`). `restaurant-customer-app` remains on its
  own local SQLite regardless of environment, since it has no shared database
  role.
- No secrets are committed to git. `.env` is git-ignored in both projects;
  only `.env.example` files (with placeholder, non-secret values) are tracked.

## Known environment note

The PHP CLI available in this environment defaults to PHP 8.4, which is
missing the `sqlite3`/`pdo_sqlite` extension and cannot run these projects
without it. Both projects were built and are verified to run under PHP 8.3
(which has `pdo_sqlite`). See `docs/PROJECT_STATE.md` for details and the
follow-up needed once `php8.4-sqlite3` is installed.
