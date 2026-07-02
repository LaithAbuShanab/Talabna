# API Conventions

These conventions apply to every endpoint added to `restaurant-backend`'s REST
API going forward. The full set of authentication/account endpoints
(register, login, logout, profile, password reset, addresses) lives under
`/api/v1` and is documented in **`docs/API_AUTH.md`** — that's the working
example of every rule below. See also `docs/CODING_STANDARDS.md` (naming
conventions), `docs/TESTING.md` (what to test and how), and `docs/SECURITY.md`
(error handling, auth, input validation).

## Base URL & versioning

- All API routes are served under `/api/v1`. `restaurant-backend/routes/api.php`
  is just a thin pointer (`Route::prefix('v1')->group(base_path('routes/api_v1.php'))`);
  the actual route definitions live in `routes/api_v1.php`.
- The customer app reaches the API only over HTTPS in any non-local
  environment; the base URL is configurable per environment
  (`RESTAURANT_BACKEND_URL` in `restaurant-customer-app/.env`).
- When a breaking change is eventually needed, introduce `routes/api_v2.php`
  the same way and prefix it with `/api/v2` — don't retrofit breaking changes
  into `v1`.

## Authentication

- Laravel **Sanctum** issues personal access (bearer) tokens; the customer app
  is a native application, not a same-site SPA, so cookie/session-based
  Sanctum auth is not used for it.
- Protected routes use the `auth:sanctum` middleware. Public endpoints (e.g.
  login, registration, public menu browsing if applicable) must be explicitly
  and deliberately marked as such — do not default new routes to public.
- Tokens are named after the requesting device (`device_name`). See
  `docs/API_AUTH.md` for the full endpoint list, rate limiting, and the
  anti-enumeration rule for forgot-password.

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
- `GET /api/v1/health` is the canonical example of the success shape
  end-to-end; see `tests/Feature/Api/HealthEndpointTest.php` and
  `tests/Feature/Api/ApiResponseFormatTest.php` for the tests that pin this
  contract down.

## Business logic placement

- Controllers stay thin: validate (via Form Request), authorize (via Policy),
  delegate to a **Service** or **Action** class, return a **Resource**.
- Anything that creates or mutates an order, payment, or other money-affecting
  record runs inside a **database transaction**.
- Status fields (order status, payment status, delivery type, etc.) are typed
  **PHP Enums**, never raw strings/integers compared by magic values.
- Concrete examples to follow when the checkout endpoint is finally built:
  `App\Services\CartPricingService` (pricing/validation, no persistence) and
  `App\Actions\CreateOrderAction` (persists inside one transaction, dispatches
  `App\Events\OrderCreated` only after commit, returns an `Order` ready for
  `App\Http\Resources\OrderResource`) — see docs/DATABASE_SCHEMA.md
  "Cart pricing" and "Order creation". Neither is wired to a route yet; a
  controller will validate the request into `CreateOrderData`, call the
  Action, and wrap the result in `ApiResponse::success(new OrderResource($order))`.

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
