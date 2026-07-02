# Project State

Last updated: 2026-07-02 — initial scaffolding, local MySQL switch + GitHub push,
unified quality standards, the full restaurant domain database schema,
realistic local dev/demo seed data, then the cart-pricing domain layer.

## Cart pricing domain layer (this task)

`restaurant-backend` only. Full detail is in the new "Cart pricing" section
of **`docs/DATABASE_SCHEMA.md`** — this is a pointer/summary plus the real
bug this task caught.

- **New**: `App\DataTransferObjects\Cart\{CartItemInputData,
  CartPricingRequestData, CartPricedOptionData, CartPricedItemData,
  CartPricingResultData}`, `App\Services\CartPricingService`,
  `App\Exceptions\CartPricingException`, `lang/{en,ar}/cart.php`.
  `CartPricingService::price()` turns a cart (product/option/quantity IDs +
  delivery type/zone + optional coupon code) into real integer amounts —
  purely a calculation, **no `Order` is persisted here**.
- **Schema additions required to make this correct** (not requested
  directly, but the explicitly-requested validations were impossible
  without them): `product_option_groups.min_select`/`max_select`
  (nullable overrides, falls back to is_required/selection_type when
  null — see docs), and `restaurant_settings.is_tax_enabled`/`tax_rate_bps`
  (basis points, tax was explicitly required to be "if enabled from
  restaurant settings" but no such setting existed before this task).
- **Security property, by construction, not by runtime check**: the DTOs
  literally have no price field anywhere — a client can only ever supply
  product/option IDs and a quantity. Every amount comes from a live
  `Product`/`OptionValue`/`DeliveryZone`/`Coupon`/`RestaurantSetting` lookup
  inside the service. Proven with a test that reprices after mutating a
  product's price mid-test and asserts the new price is picked up.
- **Validation is fail-fast**, first violation throws
  `CartPricingException` (stable `errorCode` + already-localized message
  via `lang/{locale}/cart.php`), checked in this order: cart not empty →
  per item (quantity 1-50, product exists/available, category active,
  duplicate/invalid option values, each option group's effective min/max)
  → delivery zone → combined restaurant+zone minimum order → coupon
  (exists/active, date range, global usage limit, per-user limit, its own
  minimum order) → tax (on `subtotal - discount`, gated by
  `is_tax_enabled`).
- **A real bug found and fixed**: `RestaurantSetting::current()` only set
  `restaurant_name` when lazily creating the singleton row, trusting the
  migration's column defaults for the rest. Eloquent doesn't re-read
  DB-applied defaults back into the in-memory model after `insert()`, so a
  freshly created row's `currency_code` (and everything else) came back
  `null` in PHP despite being correct in the actual database row — this
  broke `CartPricingResultData`'s non-nullable `currencyCode` on first use
  in a fresh test database. Fixed by spelling out every default explicitly
  in `current()`. Not caught by manual MySQL testing earlier (that database
  had already been seeded, so the "truly first" lazy-create path was never
  exercised) — only surfaced once a real `RefreshDatabase` test hit it.
- **A real bug found and fixed in the *previous* task's seed data**:
  `ProductSeeder` marked every attached option group `is_required = true`
  uniformly, including multi-select "Extras"/"Sauces" groups — meaning the
  seeded burgers/pizzas demanded a topping selection just to be priced,
  which is wrong (toppings are optional add-ons). Fixed to derive
  `is_required` from the group's `selection_type` (single-select forces a
  choice, multi-select doesn't). Caught by actually pricing a seeded
  product via `tinker`, not by re-reading the seeder.
  `DatabaseSeederTest`'s existing assertions didn't catch this because they
  never exercised option-group requiredness, only that the data existed.
- **Tests**: `tests/Feature/Services/CartPricingServiceTest.php`, **38
  tests** — placed under `tests/Feature`, not `tests/Unit`, per this
  project's own `docs/TESTING.md` rule that anything touching the database
  is a Feature test (the service always hits the database; that's the
  point). Covers every explicitly requested case (plain product, product
  with options, missing required option, valid coupon, expired coupon,
  minimum not met, invalid delivery zone, unavailable product, immunity to
  client-supplied prices) plus: inactive category, nonexistent
  product/coupon/zone, duplicate option selection, exceeding a group's max
  selection, inactive option value, quantity bounds, coupon not-yet-started,
  inactive coupon, coupon global/per-user usage limits, percentage discount
  capping, fixed discount never exceeding the subtotal, tax on/off, tax
  computed on the discounted amount, delivery fee zero for pickup vs. the
  zone's fee for delivery, determinism (same input twice → identical
  result), and every money field being `int`.
  Ran the suite 5x in a row to confirm no flakiness (an early version had a
  flaky test from an unset factory-random `min_order_amount` — fixed by
  pinning it explicitly wherever the test wasn't about minimums).
- Full backend suite: **97 tests / 304 assertions**, Pint clean, verified
  against the real local MySQL `Talabna` database too (migrations,
  re-seed, live pricing calls via `tinker` for the happy path, coupon,
  tax, and every explicit error case before the automated tests were even
  written).

## Realistic local dev seed data (previous task)

Expanded the schema-only seeders from the previous task into full demo data,
still `restaurant-backend` only. Full detail (what's seeded, demo login
credentials) is in **`restaurant-backend/README.dev.md`** — this section is
a pointer/summary.

- **`OptionSeeder`** now seeds exactly the 4 requested groups: **Size**,
  **Bread Type** (both single-select), **Extras**, **Sauces** (both
  multi-select) — up from the previous task's 2 groups ("Extra Toppings"
  renamed to "Extras" to match).
- **`ProductSeeder`** grew from 8 to **17 products** across the 5
  categories, each with the option groups that make sense for it attached,
  and now also creates a **`ProductImage`** row per product pointing at a
  local placeholder.
- **Placeholder images**: 5 small hand-written local SVGs, one per category,
  under `public/images/placeholders/*.svg` — deliberately not calling any
  external placeholder-image service, so seeding works offline and never
  depends on a third party's uptime. Verified served correctly by a live
  request (`GET /images/placeholders/pizza.svg` → 200) and that every
  `product_images.path` points at a file that actually exists
  (`assertFileExists` in `DatabaseSeederTest`).
- **`DeliveryZoneSeeder`**: 3 zones now (was 2), each with both a
  `delivery_fee_amount` and a `min_order_amount` set (previously
  `min_order_amount` was left null) — matches "fees and a minimum order"
  literally, not just "fees".
- **`CouponSeeder`** (new): 3 demo coupons — `WELCOME10` (10%, capped,
  1-per-user), `SAVE5` (fixed amount), `EXPIRED20` (percentage, already
  expired — a deliberate case for testing rejection once checkout logic
  exists).
- **`AdminUserSeeder`** (new, replaces the inline block that used to live in
  `DatabaseSeeder`): one `admin@example.com` / `password` account,
  `role = admin`. Password is the standard Laravel dev-seed placeholder
  (`UserFactory`'s default `Hash::make('password')`) — same one every fresh
  Laravel install ships with, **not** a production credential, and called
  out as such in `README.dev.md`.
- **`CustomerSeeder`** (new): 5 demo customers (`customer1@example.com` ..
  `customer5@example.com`, same placeholder password), each with 1-2
  addresses via `CustomerAddressFactory`. Idempotent via a plain "does this
  email already exist" check per customer (same pattern used for the admin
  account).
- **`OrderSeeder`** (new): 9 demo orders engineered to cover **every**
  `OrderStatus` value at least once (delivered appears twice: once as a
  `delivery` order, once as a `pickup` order, since the two have different
  status chains). Each order gets real `OrderItem`/`OrderItemOption` rows
  built from the actual seeded catalog (current product prices and each
  attached option group's default value — this is correct, not a
  shortcut, since "order time" for freshly-seeded demo data *is* seed
  time), plus a full, plausible `OrderStatusHistory` chain leading up to
  its final status (e.g. a `cancelled` order has
  `pending → accepted → cancelled`, not just a single `cancelled` row) and
  a `Payment` row for the two `delivered` orders.
  **Idempotency**: rather than per-row existence checks (impractical for
  orders, which don't have a natural unique business key to key off of),
  the whole seeder no-ops if *any* order already belongs to one of the 5
  demo customers. Verified genuinely idempotent — not just
  crash-free — end to end: ran `php artisan db:seed` twice in a row against
  the real local MySQL database and confirmed every table's row count was
  byte-for-byte identical after the second run.
- **`DatabaseSeeder`** now chains all 10 seeders in dependency order
  (catalog/config → `AdminUserSeeder` → `CustomerSeeder` → `OrderSeeder`,
  since orders need customers and products to already exist).
- **`restaurant-backend/README.dev.md`** (new): demo login credentials
  table, what each seeder creates, and the placeholder-image note — clearly
  headed "not for production." Linked from the root `README.md`'s
  `restaurant-backend` getting-started section.
- **Tests**: `DatabaseSeederTest` grew from 2 to **9 tests** covering: the
  baseline counts (≥4 categories, ≥15 products, ≥4 option groups, ≥2
  zones), every product having an image file that exists on disk, every
  delivery zone having both a fee and a minimum order, the coupon set
  covering both types plus an expired one, the admin account's password
  hash matching the known dev placeholder (not asserting an actual
  plaintext secret, just that it's the well-known one), every demo customer
  having at least one address, **every `OrderStatus` value being
  represented** among the seeded orders, every order having items and a
  status history whose last entry matches the order's current status, and
  the full re-run-does-not-duplicate-anything idempotency check across all
  10 seeders' data at once.
- Full suite: **59 tests / 240 assertions**, Pint clean, verified against
  both the SQLite test database and the real local MySQL `Talabna`
  database (fresh seed, re-seed for idempotency, live server boot of
  `/admin/login`, a placeholder image URL, and `/api/health`, plus an
  actual `Auth::attempt()` check that the seeded admin and customer1
  credentials really authenticate).

## Restaurant domain database schema (previous task)

Full single-restaurant ordering schema designed and built in
`restaurant-backend` only — 20 new migrations, 19 new models, 18 factories
(17 new + `UserFactory` extended), 6 seeder classes, 8 PHP enums, and 52
passing tests. Full detail — every table, every relationship, every
design decision (soft-delete choices, snapshotting, order-number generation,
append-only history, enum lifecycle rules) — is in
**`docs/DATABASE_SCHEMA.md`**; this section is a pointer/summary, not a
duplicate.

- **Entities**: every one of the 18 requested (`User`/`role` reused instead
  of a separate `AdminUser`, `CustomerAddress`, `RestaurantSetting`,
  `BusinessHour`, `Category`, `Product`, `ProductImage`, `OptionGroup`,
  `OptionValue`, `ProductOptionGroup`, `DeliveryZone`, `Coupon`,
  `CouponUsage`, `Order`, `OrderItem`, `OrderItemOption`,
  `OrderStatusHistory`, `Payment`, `DeviceToken`), plus one internal helper
  table not customer-facing: `order_number_sequences` (backs
  `Order::generateOrderNumber()`'s uniqueness guarantee under concurrency —
  see `docs/DATABASE_SCHEMA.md` for why a dedicated table was needed instead
  of just counting rows).
- **Enums** (`app/Enums/`): `UserRole`, `OrderStatus` (with
  `canTransitionTo()`/`isTerminal()` lifecycle rules), `PaymentStatus`,
  `PaymentMethod`, `DeliveryType`, `OptionSelectionType`, `CouponType`,
  `DevicePlatform`. No database `ENUM` columns anywhere — all plain
  `string` columns cast through these.
- **Money**: every monetary column is an unsigned integer (smallest currency
  unit), cast to PHP `int`. No floats/decimals for money anywhere.
- **Snapshotting**: `order_items` (product name/price),
  `order_item_options` (option group/value name/price), and `orders`
  (delivery address line/city/lat/lng) all copy what they need at order
  time, with nullable FKs back to the live rows. Verified with dedicated
  tests that deleting/editing a product, option value, or address never
  changes an existing order.
- **`users.role`**: simplest-safe choice over a separate `AdminUser` table —
  a `role` column, **excluded from mass assignment** (privilege-escalation
  guard), plus `User implements FilamentUser` so only `role = admin` can
  reach `/admin` (verified: without this, Filament lets *any* authenticated
  user into the panel by default — a real gap that would've opened up once
  customer accounts exist in the same table).
- **`order_status_histories`**: append-only, enforced in
  `App\Models\OrderStatusHistory` (`updating()`/`deleting()` hooks throw
  `LogicException`), not just documented as a convention. No `updated_at`
  column exists on the table.
- **Soft deletes**: `categories`, `products`, `option_groups`,
  `option_values`, `delivery_zones`, `coupons` — all "catalog/pricing
  configuration" an admin might retire, safe because of snapshotting. Never
  soft-deleted: `users`, transactional tables (`orders` and everything under
  it, `payments`, `coupon_usages`), `restaurant_settings`,
  `business_hours`, `product_images`, `product_option_groups`.
- **`RestaurantSetting`**: singleton via `RestaurantSetting::current()`
  (always `id = 1`, `firstOrCreate`).
- **Seeders** (`database/seeders/`): `RestaurantSettingSeeder`,
  `BusinessHourSeeder` (7 rows, Friday closed), `CategorySeeder` (5
  categories), `OptionSeeder` ("Size" + "Extra Toppings" groups with
  values), `ProductSeeder` (8 demo products, options attached where it
  makes sense), `DeliveryZoneSeeder` (2 zones). All idempotent
  (`updateOrCreate`), safe to re-run. `DatabaseSeeder` also creates one
  `admin@example.com` (role=admin) and one `test@example.com` (role=customer,
  the pre-existing default) — both guarded with existence checks so running
  the seeder twice doesn't hit the unique-email constraint (a real bug
  caught by `DatabaseSeederTest::test_running_the_seeder_twice...`).
- **Tests** (52 total, all passing): `tests/Unit/Enums/OrderStatusTest.php`
  (transition graph), `tests/Feature/Models/*` (relationships, casts,
  mass-assignment guard, Filament access control, restrict/cascade/null-on-delete
  FK behavior, append-only enforcement, snapshot integrity, coupon-usage
  uniqueness, `RestaurantSetting` singleton behavior), and
  `tests/Feature/DatabaseSeederTest.php` (seeder runs clean, and twice
  doesn't duplicate).
- Verified end-to-end against the real local MySQL `Talabna` database too
  (not just the SQLite test suite): `php artisan migrate:fresh --seed
  --force` and a live server boot of both `/admin/login` and `/api/health`.

## Quality standards (previous task)

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

- Build a checkout/place-order Action that calls
  `App\Services\CartPricingService::price()`, then actually persists the
  `Order`/`OrderItem`/`OrderItemOption`/`OrderStatusHistory`/`CouponUsage`
  rows from its result inside a DB transaction (the pricing service
  deliberately never persists anything — see `docs/DATABASE_SCHEMA.md`
  "Cart pricing"). Needs `orders.tax_amount` added at that point too.
- Build the first real API endpoints (menu browsing, cart pricing preview,
  checkout, order status) following `docs/API_CONVENTIONS.md` — Form
  Requests, Policies, API Resources around the Action above.
- Wire an actual HTTP client/service in `restaurant-customer-app` that reads
  `RESTAURANT_BACKEND_URL` and calls the backend API.
- Decide and implement the real `NATIVEPHP_APP_ID` reverse-DNS identifier.
- Install `php8.4-sqlite3` on this host (requires sudo) if the team wants to
  standardize on PHP 8.4.
- Filament Resources for the new models (currently there's a schema/domain
  layer but no admin UI yet to manage products/categories/orders through
  `/admin`).
