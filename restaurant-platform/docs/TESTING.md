# Testing

Both projects use **PHPUnit** (the framework each Laravel installer set up by
default — see `phpunit.xml` in each project). This is a deliberate choice:
don't introduce Pest or any other test framework without a real need, since
that would fragment the test suites and CI setup for no functional gain.

## Running tests

```bash
cd restaurant-backend           # or restaurant-customer-app
php artisan test                # run the full suite
php artisan test --filter=Foo   # run a subset by class/method name match
composer run test               # lint:check (Pint) + php artisan test — what CI should run
```

Both projects force an in-memory SQLite connection for tests via
`phpunit.xml` (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), regardless of
whatever database `.env` points at for local development (e.g.
`restaurant-backend`'s local `.env` currently points at a MySQL database —
tests never touch it).

## Test layout

- `tests/Feature/*` — HTTP-level tests that boot the framework: routes,
  middleware, validation, authorization, database interaction through
  Eloquent. This is where almost every new test should go.
- `tests/Unit/*` — pure logic tests with no framework boot needed (e.g. a
  Service/Action method, a DTO, an Enum's helper methods). Keep these for
  genuinely isolated logic; if a "unit" test needs `RefreshDatabase` or hits
  a route, it's a Feature test.
- API tests live under `tests/Feature/Api/` in `restaurant-backend` (see
  `tests/Feature/Api/HealthEndpointTest.php` and
  `tests/Feature/Api/ApiResponseFormatTest.php` for the current baseline).

## What to cover for new work

- **Every new API endpoint**: happy path, validation failure (422 + unified
  `errors` shape), authorization failure (401/403), and not-found (404) where
  applicable — see `docs/API_CONVENTIONS.md` for the exact response shapes to
  assert against.
- **Every Service/Action with a money or status transition**: the successful
  transition, and at least one rejected/invalid transition. Assert on the
  actual persisted state (re-fetch from the database), not just the return
  value.
- **Every Policy**: at least one allowed case and one denied case per ability.
- Prefer asserting on behavior (status codes, JSON shape, database state)
  over internal implementation details.

## Test data

- Use model factories (`database/factories/`) for setup; don't hand-build
  arrays of raw attributes when a factory already exists or should exist.
- Don't reach for mocks/fakes for the database — tests run against a real
  (in-memory SQLite) database via `RefreshDatabase`/`LazilyRefreshDatabase`
  so behavior matches what Eloquent will actually do.

## CI expectation

`composer run test` is the single command CI (or a pre-push hook) should run
per project: it fails the build on style violations (Pint) as well as test
failures, so both are enforced together.
