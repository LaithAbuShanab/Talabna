# API Conventions

These conventions apply to every endpoint added to `restaurant-backend`'s REST
API going forward. Two real endpoints exist as a working example of every rule
below: `GET /api/health` and `GET /api/user`. No restaurant-domain endpoints
exist yet — that's future, explicitly-scoped work. See also
`docs/CODING_STANDARDS.md` (naming conventions), `docs/TESTING.md` (what to
test and how), and `docs/SECURITY.md` (error handling, auth, input
validation).

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

## Responses: unified envelope

Every `/api/*` response — success or error — uses the same envelope, built
through `App\Http\Responses\ApiResponse` (`restaurant-backend/app/Http/Responses/ApiResponse.php`).
Don't hand-build `response()->json([...])` in a controller; call
`ApiResponse::success()` / `ApiResponse::error()` instead so every endpoint
stays consistent.

**Success** (`ApiResponse::success($data, $message, $status = 200)`):

```json
{
  "success": true,
  "message": "Optional message",
  "data": {}
}
```

**Error** (`ApiResponse::error($message, $errors, $status = 422)`):

```json
{
  "success": false,
  "message": "Human-readable message",
  "errors": {}
}
```

- `data`/`errors` default to `{}` (an empty object, not `null` or `[]`) when
  there's nothing to include, so clients can rely on the key always being an
  object-shaped value.
- `data` is populated from an **API Resource** (`App\Http\Resources\...`) or
  a resource collection — never a raw Eloquent model or a hand-built array of
  model attributes.
- **Exceptions are handled automatically**: `bootstrap/app.php`'s
  `withExceptions()` renders every exception raised on an `api/*` route
  through this same envelope — `ValidationException` → 422 with field errors
  in `errors`, `AuthenticationException` → 401, `AuthorizationException` →
  403, not-found (`ModelNotFoundException`/`NotFoundHttpException`) → 404,
  any other `HttpExceptionInterface` → its own status code, anything else →
  500 with a generic `"Server Error."` message (never the real exception
  message or a stack trace outside local debug mode — see
  `docs/SECURITY.md`). You don't need to catch these yourself in a
  controller/Action just to format the error response.
- Paginated list endpoints use Laravel's standard paginator, passed as `data`
  through a resource collection — never return unbounded collections.
- `GET /api/health` is the canonical example of the success shape end-to-end;
  see `tests/Feature/Api/HealthEndpointTest.php` and
  `tests/Feature/Api/ApiResponseFormatTest.php` for the tests that pin this
  contract down.

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
