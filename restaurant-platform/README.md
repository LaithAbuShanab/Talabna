# Restaurant Platform

A single-restaurant food ordering system made of two independent Laravel
projects:

- **`restaurant-backend/`** — Laravel 13 API + FilamentPHP v5 admin panel
  (`/admin`). Owns the database and all business logic.
- **`restaurant-customer-app/`** — Laravel 13 + Livewire + Tailwind, packaged
  with NativePHP Mobile v3 for Android/iOS. Talks to `restaurant-backend`
  only over its HTTPS REST API; it never touches the backend's database
  directly.

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for how the two projects
relate, [`docs/API_CONVENTIONS.md`](docs/API_CONVENTIONS.md) for API rules,
[`docs/CODING_STANDARDS.md`](docs/CODING_STANDARDS.md) for naming
conventions, [`docs/TESTING.md`](docs/TESTING.md) for test conventions,
[`docs/SECURITY.md`](docs/SECURITY.md) for the security posture,
[`CONTRIBUTING.md`](CONTRIBUTING.md) for the contribution workflow, and
[`docs/PROJECT_STATE.md`](docs/PROJECT_STATE.md) for the current status of
the codebase.

## Requirements

- PHP `^8.3` with the `pdo_sqlite`/`sqlite3` extensions (required for local
  development). See the PHP version note in `docs/PROJECT_STATE.md` — the
  system default `php` in this environment is 8.4 without SQLite support,
  so both projects were set up and verified under PHP 8.3.
- Composer 2.x
- Node.js 20+ / npm (for Tailwind/Vite asset builds)
- For building the customer app's native binaries: Android SDK (and Xcode on
  macOS for iOS) — not required just to run the Laravel side.

## Getting started — `restaurant-backend`

```bash
cd restaurant-backend
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite   # skip this if you're using MySQL/PostgreSQL instead — see .env
php artisan migrate
php artisan make:filament-user   # create your first admin account
```

Admin panel: `http://localhost:8000/admin`

## Getting started — `restaurant-customer-app`

```bash
cd restaurant-customer-app
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install && npm run build
```

Set `RESTAURANT_BACKEND_URL` in `.env` to point at a running
`restaurant-backend` instance before wiring up any API calls.

## Common commands

Run these from inside the project you're working on
(`restaurant-backend/` or `restaurant-customer-app/`):

| Task | Command |
|---|---|
| Run tests | `php artisan test` (or `composer run test`, which also lints first) |
| Run one test | `php artisan test --filter=TestClassOrMethodName` |
| Format code (auto-fix) | `composer run lint` (or `php vendor/bin/pint`) |
| Check formatting only (no changes, CI-style) | `composer run lint:check` |
| Run migrations | `php artisan migrate` |
| Roll back last migration batch | `php artisan migrate:rollback` |
| Start the backend/app server | `php artisan serve` (defaults to `http://localhost:8000`) |
| Start the queue worker | `php artisan queue:work` (or `queue:listen` while developing, so code changes are picked up without a restart) |
| Start everything at once (server + queue + logs + Vite) | `composer run dev` |

`restaurant-customer-app` only — NativePHP Mobile:

| Task | Command |
|---|---|
| **NativePHP development run** — start a dev server + QR code so the app can be opened live on a phone/emulator, with hot reload | `php artisan native:jump` |
| Build, package, and run the native Android/iOS shell (heavier: full build) | `php artisan native:run` |
| Re-run the NativePHP install/scaffold step (already done once — only needed if `nativephp/` is deleted or reset) | `php artisan native:install` |

See NativePHP's own docs for building signed release binaries — that's out
of scope for local development.
