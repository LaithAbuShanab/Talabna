# Security

## Reporting a vulnerability

If you find a security issue in this codebase, do not open a public GitHub
issue. Report it privately to the project maintainer first so it can be
fixed before it's disclosed.

## Secrets

- No secrets, API keys, or credentials are ever committed to git. Only
  `.env.example` files (placeholder values, no real secrets) are tracked;
  real `.env` files are git-ignored in both projects.
- If a secret is ever committed by accident, rotating/revoking it is not
  optional — removing it from a later commit does not remove it from git
  history. Rotate first, then clean history if needed.
- Personal Access Tokens, SSH keys, and similar credentials used to interact
  with GitHub or other services from this environment are never written into
  project files or persisted in git config; they're supplied at the moment
  they're needed and only used for that one operation.

## Error handling: no internal details leak to clients

- `restaurant-backend`'s API (`routes/api.php`, any route under `/api/*`)
  renders every exception through the unified `{success, message, errors}`
  envelope (`App\Http\Responses\ApiResponse`, wired in
  `bootstrap/app.php`'s `withExceptions()`), covering validation errors,
  authentication/authorization failures, not-found, and any uncaught
  exception. See `docs/API_CONVENTIONS.md` for the exact shape.
- **In production (`APP_DEBUG=false`)**: uncaught exceptions are reported to
  the client as a generic `"Server Error."` message — the real exception
  message, class, file, line, and stack trace are never included in the
  response body. They still go to the configured log channel
  (`config/logging.php`) for developers to inspect server-side.
- **Locally (`APP_DEBUG=true`)**: the real exception message is included to
  speed up debugging, but the stack trace itself is still never put in the
  JSON body by our custom renderer.
- `tests/Feature/Api/ApiResponseFormatTest.php` asserts that `trace`,
  `exception`, `file`, and `line` keys are never present in an API error
  response — treat a failure of that test as a security regression, not a
  flaky test to skip.
- `APP_DEBUG` must be `false` in every deployed (non-local) environment. This
  is a deploy/ops requirement, not just a default — verify it explicitly when
  setting up a new environment.

## Authentication & authorization

- `restaurant-backend` uses **Laravel Sanctum** bearer tokens for API auth
  (`auth:sanctum` middleware), appropriate for `restaurant-customer-app`
  being a separate native mobile client rather than a same-site SPA. Don't
  switch to cookie/session-based Sanctum auth for the customer app without a
  concrete reason (e.g. a future first-party web SPA).
- The Filament admin panel (`/admin`) has **no public self-registration** —
  confirmed by the absence of `->registration()` in
  `app/Providers/Filament/AdminPanelProvider.php`. Admin accounts are
  provisioned by an existing admin or via `php artisan make:filament-user`.
  Do not add `->registration()` to the admin panel without an explicit,
  deliberate decision to allow public admin sign-up (this would very likely
  be wrong for a single-restaurant admin panel).
- Every new API endpoint must go through a **Policy** or **Gate** check for
  authorization — don't rely on "the route is only called from the app UI"
  as an authorization boundary; the API is reachable directly.

## Input validation & mass assignment

- Every endpoint that accepts input validates it via a **Form Request**
  class, never inline `$request->validate()` scattered in controllers (see
  `docs/API_CONVENTIONS.md` and `docs/CODING_STANDARDS.md`).
- Eloquent models declare an explicit allow-list of mass-assignable
  attributes (Laravel 13's `#[Fillable([...])]` attribute or `$fillable`) —
  never `$guarded = []` on a model that accepts any user input.

## Money

- Prices/totals sent by a client are **advisory only** and never trusted;
  the server always recalculates and validates the authoritative amount
  before persisting or charging anything (see `docs/API_CONVENTIONS.md`).
  This prevents a tampered client request from setting an arbitrary price.

## Dependencies

- Run `composer audit` periodically (and definitely before a production
  deploy) in both projects to check for known vulnerabilities in installed
  packages.
- Keep Laravel, Filament, Sanctum, and NativePHP Mobile on supported/patched
  versions; don't pin to an old major version without a documented reason
  (see `docs/PROJECT_STATE.md` "Decisions & assumptions" for the versioning
  rationale already on record).

## Transport

- `restaurant-customer-app` must talk to `restaurant-backend` over **HTTPS**
  in any non-local environment (`RESTAURANT_BACKEND_URL`, see
  `docs/ARCHITECTURE.md`) — plain HTTP is only acceptable for same-machine
  local development.
