# API Conventions

These conventions apply to every endpoint added to `restaurant-backend`'s REST
API going forward. No restaurant-domain endpoints exist yet (this scaffolding
task intentionally created none) — this document sets the ground rules before
that work starts.

## Base URL & versioning

- All API routes are registered in `restaurant-backend/routes/api.php` and are
  served under `/api`.
- The customer app reaches the API only over HTTPS in any non-local
  environment; the base URL is configurable per environment
  (`RESTAURANT_BACKEND_URL` in `restaurant-customer-app/.env`).
- Introduce a version prefix (e.g. `/api/v1/...`) before the first breaking
  change is needed; until then, treat the unversioned API as `v1` implicitly.

## Authentication

- Laravel **Sanctum** issues personal access (bearer) tokens; the customer app
  is a native application, not a same-site SPA, so cookie/session-based
  Sanctum auth is not used for it.
- Protected routes use the `auth:sanctum` middleware. Public endpoints (e.g.
  login, registration, public menu browsing if applicable) must be explicitly
  and deliberately marked as such — do not default new routes to public.

## Request validation

- Every endpoint that accepts input validates it through a dedicated **Form
  Request** class (`App\Http\Requests\...`), not inline `$request->validate()`
  in controllers.
- Authorization for the request (can this user perform this action) is
  expressed via the Form Request's `authorize()` method and/or a **Policy**,
  not ad-hoc checks scattered in controllers.

## Responses

- Every endpoint returns data through an **API Resource**
  (`App\Http\Resources\...`), never raw Eloquent models or arrays built by
  hand in the controller.
- Standard success envelope: the resource's natural JSON shape, wrapped by
  Laravel's default `data` key for single resources / resource collections.
- Standard error shape: Laravel's default validation error / exception JSON
  (`message`, `errors` for 422s). Do not invent a bespoke error envelope
  without updating this document.
- Paginated list endpoints use Laravel's standard paginator output (cursor or
  length-aware, chosen per endpoint) — never return unbounded collections.

## Business logic placement

- Controllers stay thin: validate (via Form Request), authorize (via Policy),
  delegate to a **Service** or **Action** class, return a **Resource**.
- Anything that creates or mutates an order, payment, or other money-affecting
  record runs inside a **database transaction**.
- Status fields (order status, payment status, delivery type, etc.) are typed
  **PHP Enums**, never raw strings/integers compared by magic values.

## Money and dates

- All monetary amounts in requests and responses are integers in the smallest
  currency unit (e.g. fils/cents) — never floats, never decimal strings.
- Prices/totals sent by the client are **advisory only**; the server always
  recalculates and validates them from its own data before persisting or
  responding.
- All dates/timestamps are stored and transmitted in UTC (ISO 8601). Any
  timezone conversion for display happens on the client or at the
  presentation layer, never by mutating stored values.

## Eager loading / performance

- List/detail endpoints that touch relationships use eager loading
  (`with()`/`load()`) deliberately chosen per endpoint to avoid N+1 queries;
  this should be verified (e.g. via `assertQueryCountLessThan` or manual
  `DB::listen` checks) when adding a new endpoint with relationships.

## Testing

- Every new endpoint gets Feature tests covering: happy path, validation
  failure, authorization failure (401/403), and — where relevant — the
  money/transactional behavior.
