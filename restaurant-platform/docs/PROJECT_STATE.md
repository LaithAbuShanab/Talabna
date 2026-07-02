# Project State

Last updated: 2026-07-02 — initial scaffolding, local MySQL switch + GitHub push,
then unified quality standards (Pint, unified API envelope, docs) across both projects.

## Quality standards (this task)

Applies to both projects unless noted. See `CONTRIBUTING.md`,
`docs/CODING_STANDARDS.md`, `docs/TESTING.md`, `docs/SECURITY.md`, and the
"Responses: unified envelope" section of `docs/API_CONVENTIONS.md` for the
full detail — this is a summary of what changed and why.

- **Pint**: both projects now have an explicit `pint.json`
  (`{"preset": "laravel", "rules": {"declare_strict_types": true}}`) —
  `restaurant-backend` didn't have one before (it was relying on Pint's
  implicit default). The `declare_strict_types` rule auto-added
  `declare(strict_types=1);` to every existing PHP file in both projects
  (config, routes, migrations, lang files, app code, tests) — verified both
  full test suites still pass after this ran.
- **Test framework**: unchanged — both projects keep **PHPUnit** (not Pest),
  per the explicit instruction not to switch frameworks without a reason.
- **Composer scripts for parity**: `restaurant-customer-app` (Livewire
  starter kit) already had `lint`, `lint:check`, and a `test` script that
  chains lint + Larastan (`types:check`) + `php artisan test`.
  `restaurant-backend` didn't — added matching `lint`/`lint:check` scripts
  and wired `lint:check` into its `test` script too, so `composer run test`
  now means the same thing (lint + test) in both projects.
- **Unified API envelope**: `App\Http\Responses\ApiResponse` (new,
  `restaurant-backend/app/Http/Responses/ApiResponse.php`) is the only way
  API responses should be built —
  `success(data, message, status=200)` → `{success:true, message, data}`,
  `error(message, errors, status=422)` → `{success:false, message, errors}`.
  Wired into `bootstrap/app.php`'s `withExceptions()` so **every** exception
  on an `api/*` route — validation, auth, authz, not-found, or anything
  uncaught — renders through this same envelope automatically; controllers
  don't need to catch these themselves.
- **`GET /api/health`** (new, `routes/api.php`) is the first real endpoint
  and doubles as the working example of the success envelope. `GET
  /api/user` (Sanctum's scaffolded route) was updated to wrap its response
  in `ApiResponse::success()` too, for consistency — still not going through
  a full API Resource since it's Sanctum's utility route, not domain data.
- **Real bug found and fixed**: unauthenticated requests to `/api/user`
  **without** an `Accept: application/json` header (i.e. what `curl` sends by
  default, and what a naive HTTP client might send) returned a **500**, not
  401 — Laravel's `ApplicationBuilder` registers a default
  `redirectGuestsTo(fn () => route('login'))`, and this backend has no `login`
  route (Filament's admin auth is a separate guard/route), so resolving it
  threw `RouteNotFoundException`. Fixed in `bootstrap/app.php`'s
  `withMiddleware()`: `$middleware->redirectGuestsTo(fn () => null)`. Caught
  by live `curl` testing, **not** by the initial PHPUnit tests — `getJson()`
  sends `Accept: application/json` and so never hit the buggy path. Added
  `test_unauthenticated_api_request_without_accept_header_still_returns_json_401`
  (using plain `get()`, not `getJson()`) as a regression guard so this can't
  silently come back.
- **New tests**: `tests/Feature/Api/HealthEndpointTest.php` (health shape) and
  `tests/Feature/Api/ApiResponseFormatTest.php` (401/404 unified error shape,
  the Accept-header regression above, and a check that `trace`/`exception`/
  `file`/`line` never appear in an API error response).
- **`app/Concerns/ProfileValidationRules.php`** (customer-app, part of the
  official Livewire starter kit, not something we wrote): fixed a pre-existing
  PHPDoc return-type gap (`Illuminate\Validation\Rules\Unique` wasn't listed
  in the union type) that made Larastan (`composer run test`'s `types:check`
  step) fail on code we didn't touch otherwise. Docblock-only fix, no
  behavior change — needed because our docs/README now tell people to rely on
  `composer run test` passing.
- New root docs: `CONTRIBUTING.md`, `docs/CODING_STANDARDS.md` (naming
  conventions for Models/Services/Actions/DTOs/Enums/API Resources/Filament
  Resources), `docs/TESTING.md`, `docs/SECURITY.md`. `README.md`'s command
  reference expanded into an explicit table (tests, formatter, migrations,
  server, queue worker, NativePHP dev run via `native:jump` vs. full
  `native:run`).
- Verified at the end: `composer run test` passes clean in both projects
  (backend: 7 tests / 25 assertions + Pint clean; customer-app: 33 tests /
  81 assertions + Pint clean + Larastan 0 errors), and live `curl` checks
  against a running backend server confirm the envelope shape, the 401 fix,
  and that no stack trace ever appears in an API error response.

## Version control

- Git repo root is `talabna-platform/` (one level above `restaurant-platform/`),
  initialized fresh and pushed as the initial commit to
  `https://github.com/LaithAbuShanab/Talabna.git` (branch `main`).
- Verified before commit: only `.env.example` files are tracked (no real
  `.env`), and `vendor/`, `node_modules/`, and `restaurant-customer-app/nativephp/`
  (Android build output, ~72MB) are all excluded per the nested `.gitignore`
  files. 243 files in the initial commit.
- Pushed over HTTPS using a one-time Personal Access Token supplied directly
  in the `git push`/`git fetch` command line — the token was never written to
  `.git/config`, any repo file, or persisted anywhere. `git remote -v` shows
  a plain tokenless URL. **Follow-up**: future pushes from this environment
  will need credentials again (either add the environment's SSH public key at
  github.com/settings/keys, or supply a fresh token) since none are stored.

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
- **Local `.env` now uses MySQL, not SQLite**: `DB_CONNECTION=mysql`,
  `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=Talabna`,
  `DB_USERNAME=root`, `DB_PASSWORD=root`, pointing at a pre-existing local
  `Talabna` database. Migrations re-run successfully against it (`users`,
  `cache_*`, `jobs`, `job_batches`, `failed_jobs`, `sessions`,
  `password_reset_tokens`, `personal_access_tokens`, `migrations` tables
  created); `/admin/login` verified live over this connection.
  `.env.example` intentionally still documents the SQLite-by-default /
  MySQL-or-PostgreSQL-in-production pattern from `config/database.php` — only
  this machine's real `.env` (git-ignored) was switched.
- The test suite is unaffected by this: `phpunit.xml` forces
  `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` for all test runs regardless
  of `.env`, so `php artisan test` still uses in-memory SQLite.
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
