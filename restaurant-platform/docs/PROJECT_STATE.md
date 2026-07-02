# Project State

Last updated: 2026-07-02 — initial scaffolding task.

## What exists

Two independent Laravel projects under `restaurant-platform/`, both freshly
scaffolded and verified to boot, migrate, format, and pass their test suites.
**No restaurant-domain tables, models, or business logic exist yet** — this
was intentionally out of scope for this task.

### `restaurant-backend`

- Laravel `v13.18.0`, PHP `^8.3`.
- Laravel Sanctum `v4.3.2` installed via `php artisan install:api`;
  `App\Models\User` uses `Laravel\Sanctum\HasApiTokens`. Routes:
  `sanctum/csrf-cookie`, `api/user` (protected).
- FilamentPHP `v5.6.8` panel builder installed via `php artisan
  filament:install --panels`. Panel id `admin`, mounted at `/admin`
  (`app/Providers/Filament/AdminPanelProvider.php`). No `->registration()`
  call, so public self-registration is disabled (confirmed: `GET
  /admin/register` → 404).
- `DB_CONNECTION=sqlite` in `.env` for local dev; `config/database.php` and
  `.env(.example)` are already set up to switch to `mysql`/`pgsql` for
  production (commented block with host/port/database/username/password).
- Locale defaults: `APP_LOCALE=ar`, `APP_FALLBACK_LOCALE=en`. Filament's
  admin panel automatically renders `lang="ar" dir="rtl"` and translated
  UI strings out of the box (verified via a live request to `/admin/login`).
- `lang/ar/{auth,pagination,passwords,validation}.php` and the matching
  `lang/en/*` were published (`php artisan lang:publish`) and translated by
  hand (see "Decisions" below).
- Migrations applied: `users`, `cache`, `jobs`, `personal_access_tokens`.
- Tests: `php artisan test` → 2 passed, 2 assertions.
- Formatter: `php vendor/bin/pint` → fixed 1 file
  (`bootstrap/providers.php`), re-ran tests after, still passing.

### `restaurant-customer-app`

- Laravel `v13.18.0`, scaffolded with `laravel new --livewire` (the official
  Livewire starter kit: Blade + Livewire `v4.3.3` + Tailwind v4 + Flux UI +
  Fortify auth scaffolding), then layered with NativePHP Mobile `3.3.6`
  (`php artisan native:install`).
- NativePHP install created `nativephp/` (Android project + downloaded PHP
  binaries, ~72MB, entirely git-ignored via its own `nativephp/.gitignore`
  containing `*`), `nativephp.lock` (records the native PHP build
  version/ICU flag — small, intended to be committed), and a `native` CLI
  wrapper script. No additional native device features (camera, push,
  biometrics, etc.) were added — base install only, as requested.
- `NATIVEPHP_APP_ID` set to the placeholder `com.example.restaurant.customer`
  in both `.env` and `.env.example` (the installer's auto-generated random id
  was replaced with a clear placeholder — update this to the real reverse-DNS
  app id before any store submission).
- `RESTAURANT_BACKEND_URL` (+ `RESTAURANT_BACKEND_API_TIMEOUT`) added to
  `.env`/`.env.example` as the (currently unused) configuration point for
  where this app will call the backend's REST API. No HTTP client / API
  service code was written yet — that's future, explicitly-scoped work per
  `docs/API_CONVENTIONS.md`.
- Locale defaults: `APP_LOCALE=ar`, `APP_FALLBACK_LOCALE=en`. All Blade
  layout root `<html>` tags (`layouts/app/header.blade.php`,
  `layouts/app/sidebar.blade.php`, `layouts/auth/{card,split,simple}.blade.php`,
  `welcome.blade.php`) now compute `dir="rtl"`/`dir="ltr"` from
  `app()->getLocale()`. Verified live: `GET /` renders `lang="ar" dir="rtl"`.
- `lang/ar/{auth,pagination,passwords,validation}.php` hand-translated
  (identical keys to `lang/en/*`, diffed to confirm before copying).
- Migrations applied: `users`, `cache`, `jobs`, `passkeys`, two-factor auth
  columns.
- Tests: `php artisan test` → 33 passed, 81 assertions.
- Formatter: `php vendor/bin/pint` → passed, no changes needed.
- `npm install && npm run build` succeeded (Tailwind/Vite assets built).

## Decisions & assumptions

- **PHP 8.3 vs 8.4**: this environment's default `php`/`composer` resolve to
  PHP 8.4.22, which lacks the `sqlite3`/`pdo_sqlite` extension, and installing
  it requires `sudo` (a password we don't have). The user chose to use PHP
  8.3 (which already has `pdo_sqlite`) for these projects. Both projects were
  created, installed, and tested end-to-end under PHP 8.3 for consistency
  (`composer.json` → `"php": "^8.3"` in both). **Follow-up**: once
  `sudo apt install php8.4-sqlite3` is run, the projects can be re-verified
  under 8.4 and the constraint bumped if desired — not done automatically
  since it wasn't requested and changing the platform PHP version is outside
  this task's scope.
- **NativePHP starter choice**: used `laravel new --livewire` (official
  Laravel starter kit, latest Livewire/Tailwind/Fortify) + `composer require
  nativephp/mobile` (latest 3.3.6) rather than the community
  `nativephp/mobile-starter` or `nativephp/mobile-api-starter-kit` templates,
  because both of those pin `laravel/framework: ^12.0` and, in the API
  starter kit's case, an older `nativephp/mobile: ^2.1.0` — not the latest
  stable/compatible combination the task asked for.
- **Arabic translation files**: `laravel-lang/lang` was tried first (the
  common community package for this) but its actual publishing command
  lives in the separate `laravel-lang/publisher` package, which failed to
  install in this environment (`ext-bcmath` missing, also blocked by the
  same no-`sudo` constraint). Rather than leave Arabic support half-done,
  `lang/ar/{auth,pagination,passwords,validation}.php` were written by hand
  to match the exact keys published by Laravel's own `lang:publish` for this
  Laravel version (diffed against `lang/en/*` first to confirm key parity
  between the two projects). No other locales were added.
- **Sanctum auth model**: bearer tokens (`auth:sanctum` + `install:api`
  scaffolding), not cookie/session SPA auth — appropriate since the customer
  app is a separate native mobile application, not a same-site SPA. This
  should hold unless a future task explicitly needs stateful/cookie auth
  (e.g. a future admin-facing SPA).
- **No restaurant/order/menu tables or models were created**, per explicit
  instruction. `docs/API_CONVENTIONS.md` documents the rules those will need
  to follow (Form Requests, API Resources, Enums, Policies, transactions,
  integer money, UTC dates) once that work starts.

## How to run things

See the root [`README.md`](../README.md) for setup and dev-server commands
for both projects. Formatter/tests per project:

```bash
cd restaurant-backend   # or restaurant-customer-app
php vendor/bin/pint
php artisan test
```

(Use a PHP 8.3 binary if your default `php` is 8.4 without `pdo_sqlite` —
see "Decisions & assumptions" above.)

## Next likely tasks (not started)

- Design the restaurant domain schema (menu items, categories, orders, order
  items, payments) in `restaurant-backend`, with Enums for order/payment
  status and delivery type.
- Build the first real API endpoints following `docs/API_CONVENTIONS.md`.
- Wire an actual HTTP client/service in `restaurant-customer-app` that reads
  `RESTAURANT_BACKEND_URL` and calls the backend API.
- Decide and implement the real `NATIVEPHP_APP_ID` reverse-DNS identifier.
- Install `php8.4-sqlite3` on this host (requires sudo) if the team wants to
  standardize on PHP 8.4.
