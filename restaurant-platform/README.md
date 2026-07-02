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
and [`docs/PROJECT_STATE.md`](docs/PROJECT_STATE.md) for the current status
of the codebase.

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
touch database/database.sqlite
php artisan migrate
php artisan make:filament-user   # create your first admin account
composer run dev                 # serves the app + queue + Vite
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
composer run dev                 # serves the app + queue + Vite
```

Set `RESTAURANT_BACKEND_URL` in `.env` to point at a running
`restaurant-backend` instance before wiring up any API calls.

To build/run the native Android/iOS shell (once native features are actually
being added), see NativePHP's docs: `php artisan native:run`.

## Formatting & tests

Run these from inside each project directory after making changes:

```bash
php vendor/bin/pint      # code style
php artisan test         # PHPUnit test suite
```
