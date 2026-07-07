# Customer App: API Client Architecture

`restaurant-customer-app`'s first real application architecture: a
centralized API client, typed exceptions/DTOs, secure token storage, and
the generic error/offline/dev-health screens every future feature builds
on. This document is the map of that structure — see
`docs/ARCHITECTURE.md` for how this app relates to `restaurant-backend`
overall.

## Current setup (checked before building anything)

- Laravel `13.18.0`, PHP `^8.3` (dev PHP 8.3 in this environment — same
  note as `restaurant-backend`, see `docs/PROJECT_STATE.md`).
- `nativephp/mobile` **`3.3.6`** already installed (base install only —
  see `docs/ARCHITECTURE.md`). It ships more than "just the base install"
  suggested: `Native\Mobile\SecureStorage` (Keychain/Keystore) and
  `Native\Mobile\Network` (connectivity status) are both already present
  and used by this task — no new native package was installed.
- Livewire **v4.1** + `livewire/blaze` (single-file "Blaze" components,
  the `⚡`-prefixed files under `resources/views/pages/`) + Flux UI v2 —
  already this app's established UI stack; the new screens follow the
  exact same pattern as the existing settings pages.
- `RESTAURANT_BACKEND_URL`/`RESTAURANT_BACKEND_API_TIMEOUT` env vars
  already existed (from the initial scaffolding task) but nothing read
  them yet — no HTTP client code existed in this app before this task.

## Directory structure

| Directory | Purpose |
|---|---|
| `app/Services/Api` | `ApiClient` — the single seam to `restaurant-backend` |
| `app/Data/Api` | DTOs for API results (`ApiResponseData`, `HealthCheckData`) |
| `app/Actions/Api` | Use cases that call `ApiClient` (`CheckApiHealthAction`) |
| `app/Stores` | This app's state layer (`AuthTokenStore`, `NetworkStatusStore`) |
| `app/Support` | `SecureStorage`, `SafeLog` |
| `app/Exceptions/Api` | Nine typed exceptions `ApiClient` can throw |

### Why `Stores`, not a JS state-management library

This is a server-rendered Livewire app, not an SPA — there's no client-side
store to reach for (Redux/Pinia/etc. don't apply). "State management
مناسب للتقنية الحالية" here means: a small number of plain PHP classes
holding state that needs to survive *across* a single Livewire component's
lifecycle (an auth token, "are we online") backed by Laravel's own
server-side primitives (session, the native bridge) — not
component-local `public` properties, which only live for one component.

## `App\Services\Api\ApiClient`

The **only** class in this app that calls the `Http` facade against
`restaurant-backend`. Every method returns `App\Data\Api\ApiResponseData`
on success or throws a specific exception — never a raw array, never a
bare `Illuminate\Http\Client` exception.

```php
$client->get('/api/v1/health');
$client->post('/api/v1/orders', ['delivery_type' => 'pickup', ...]);
```

### Base URL / timeout / config

`config/api.php` → `RESTAURANT_BACKEND_URL` / `RESTAURANT_BACKEND_API_TIMEOUT`
/ `RESTAURANT_BACKEND_API_RETRY_TIMES` / `RESTAURANT_BACKEND_API_RETRY_DELAY_MS`.
No other class reads these env vars directly — always through config.

### HTTPS in production ("استخدام HTTPS في production")

`ApiClient`'s constructor throws a `RuntimeException` if
`app()->isProduction()` and the base URL doesn't start with `https://`. A
misconfigured `.env` fails loudly at boot in production instead of
silently sending API traffic (including, eventually, the auth token) over
plain HTTP. Non-production environments (local dev against
`http://localhost:8000`) are unaffected.

### Certificate validation ("عدم تعطيل certificate validation")

Never disabled. `ApiClient` never calls `->withoutVerifying()` or sets
`verify: false` — nothing in this codebase does. If a future task ever
needs a custom CA bundle, that's `->withOptions(['verify' => $caBundlePath])`,
never `false`.

### JSON parsing

`Response::json()` is used for every response; a body that isn't valid
JSON becomes `ApiUnexpectedResponseException` rather than a fatal error.
Successful bodies are wrapped in `App\Data\Api\ApiResponseData`
(`{success, message, data}`), mirroring `restaurant-backend`'s own unified
envelope (its `docs/API_CONVENTIONS.md`) exactly.

### Retry — "retry محدود فقط للطلبات الآمنة"

Only `GET`/`HEAD` ever retry, and only a genuine transport-level failure:

```php
$request->retry(
    $this->retryTimes,       // config('api.restaurant_backend.retry.times'), default 2
    $this->retryDelayMs,     // default 200ms
    when: fn ($exception) => $exception instanceof ConnectionException,
    throw: false,
);
```

Both the `when` callback and `throw: false` are load-bearing — see "Bugs
found while testing" below for exactly what breaks without either.
`POST`/`PUT`/`PATCH`/`DELETE` are never retried at all: retrying a
non-idempotent request on a network hiccup risks silently duplicating a
side effect (e.g. placing an order twice), which is strictly worse than
surfacing the error once.

### Failure handling — the exception hierarchy

| Exception | When | Maps to |
|---|---|---|
| `ApiOfflineException` | `NetworkStatusStore` already knows the device is offline — checked *before* the request is attempted | offline screen |
| `ApiTimeoutException` | `ConnectionException` whose message indicates a timeout | offline screen |
| `ApiConnectionException` | Any other `ConnectionException` (DNS failure, refused, ...) — also the fallback for "offline" when the native bridge isn't present | offline screen |
| `ApiUnauthorizedException` | 401 or 403 | caller should clear `AuthTokenStore` and prompt re-login |
| `ApiValidationException` | 422 (carries `errors`) | render field-level messages |
| `ApiRateLimitedException` | 429 (carries `retryAfterSeconds`) | show a "try again in Ns" message |
| `ApiServerException` | 5xx | generic error screen |
| `ApiUnexpectedResponseException` | Non-JSON body, or an unmapped status | generic error screen |

All extend `App\Exceptions\Api\ApiException`; `ApiOfflineException`/
`ApiTimeoutException`/`ApiConnectionException` share the common
`ApiConnectivityException` parent (the "no response at all" category);
the HTTP-status ones share `ApiHttpException` (carries `statusCode`/`body`).

### Safe logging — "logging آمن دون tokens أو كلمات مرور"

Every request/response `ApiClient` logs goes through
`App\Support\SafeLog::redact()` first: exact-match keys
(`token`, `password`, `secret`, `api_key`, `authorization`, `cookie`, ...)
and anything ending in `_token`/`_password`/`_secret`/`_key`
(case-insensitive, recursive into nested arrays) become `[REDACTED]`. The
response **body** is never logged at all — only status code, method, URL,
and a `request_id` correlating the two log lines per call. No header dump
is ever built for logging, so there's no `Authorization` header to forget
to redact in the first place.

## DTOs — "DTOs لنتائج API المهمة"

- `App\Data\Api\ApiResponseData` — the generic `{success, message, data}`
  envelope every successful call returns.
- `App\Data\Api\HealthCheckData` — `{status, timestamp, responseTimeMs}`,
  built by `App\Actions\Api\CheckApiHealthAction` for the dev health
  screen.

**Not** used as public Livewire component properties directly — see
"Bugs found" below for why.

## Secure token storage

### "استخدام التخزين الآمن المتاح في NativePHP"

`nativephp/mobile` **is installed and does ship** `Native\Mobile\SecureStorage`
(native Keychain on iOS, EncryptedSharedPreferences/Keystore on Android) —
so this isn't the "not installed yet" case the task anticipated as a
fallback trigger. `App\Support\SecureStorage` delegates to it directly.

### The real wrinkle, and the "temporary, documented abstraction"

`Native\Mobile\SecureStorage`/`Network` (and everything else in the
package) only do anything useful when a real device/bridge answers
`nativephp_call()`. That global function is **always defined** once the
package autoloads — it ships a Jump-hybrid-mode fallback implementation —
so `function_exists('nativephp_call')` cannot distinguish "real bridge" from
"no bridge" (this was tried first and caused a real bug — see below).
`App\Support\SecureStorage` instead does what the package's own classes do
internally: attempt the native call, and treat a negative/`null` result
(what happens with no device attached — local `php artisan serve`, or this
test suite) as license to fall back to Laravel's server-side session, with
the value **explicitly `Crypt`-encrypted** (keyed off `APP_KEY`) before
it's ever written there.

- Never plaintext.
- Never `localStorage` — "عدم تخزين token في localStorage الويب" holds in
  *both* the native and the fallback path, since neither ever touches
  browser storage at all.
- On a real device the native call always succeeds, so the fallback is
  simply never reached there — it's a genuine stand-in for the one
  environment the real secure storage can't reach, not a second storage
  location that could ever disagree with it.

`App\Stores\AuthTokenStore` is the one place a future backend Sanctum
token (from `POST /api/v1/auth/login`) would be read/written/cleared —
built on top of `SecureStorage`, never called directly by anything else.
Not yet wired to an actual login flow; that's future work this is the
storage seam for.

## Screens

- **`/error`** — generic, static, never shows a raw exception message
  (matching `restaurant-backend`'s own "no internal details leak" rule
  applied client-side too). The redirect target for an `ApiException`
  with nothing more specific to show.
- **`/offline`** — polls `NetworkStatusStore::isOnline()` every 5 seconds
  (`wire:poll.5s`) and redirects home automatically once it's true, plus a
  manual "try again" button. The target for any `ApiConnectivityException`.
- **`/dev/health`** — "health check development screen". Only registered
  as a route when `app()->environment('local')` (checked in
  `routes/web.php` at boot) — it doesn't exist at all outside local
  development, not just hidden. Calls `CheckApiHealthAction` and shows the
  result or the caught exception's type/message.

## Bugs found while building this (via tests and live verification, not just inspection)

1. **`retry()`'s own default (`$throw = true`) turns a bad *response* into
   an exception too** — the fix was `throw: false`. But that alone still
   wasn't enough:
2. **`retry($times, $delay, throw: false)` retries a plain 4xx/5xx
   response anyway**, on every attempt but the last — a completely
   separate check inside `PendingRequest::send()` from the `$throw` flag.
   Fixed with an explicit `when` callback restricting retries to
   `ConnectionException` instances only. Caught by
   `test_retry_never_converts_a_4xx_response_into_an_exception_that_bypasses_status_handling`
   in `ApiClientTest` — without the fix, a validation-error GET would have
   been silently retried.
3. **`function_exists('nativephp_call')` is always true** — see "The real
   wrinkle" above. The first implementation of `SecureStorage`/
   `NetworkStatusStore` used this check and was subtly wrong in a way that
   only surfaced when writing tests for the fallback path (`SecureStorage::set()`
   returned `false` even for a plain session-only write, because it
   thought a real native bridge was present and available).
4. **A DTO can't be a public Livewire component property** — the
   dev-health screen originally stored `?HealthCheckData $result` as a
   public property; Livewire's dehydration only supports a fixed set of
   types (scalars, arrays, Enums, Models, Collections, DateTime, ...), not
   an arbitrary readonly class, and threw `Property type not supported in
   Livewire`. Fixed by storing the individual scalar fields instead.
5. **Wrapping a Blaze page's content in `<x-layouts::auth>` breaks
   Livewire's single-root-element requirement** — that layout component's
   own output has sibling top-level nodes (`@persist('toast')`/
   `@fluxScripts` after the main wrapping `<div>`), which is fine for a
   plain (non-Livewire) Blade view like the login page, but a full Livewire
   page component must resolve to exactly one root element for its *own*
   template. Fixed with the `#[Layout('layouts.auth.simple')]` attribute
   instead, which wraps the component's single-root output in the layout
   as a separate step, outside Livewire's root-detection.

## Testing

`tests/Feature/Services/Api/ApiClientTest.php` (21 tests) — success,
every exception type with its exact mapping, the retry contract (limited,
safe-methods-only, and the regression guard for finding #2 above),
auth-token attachment, and the HTTPS-in-production guard — all via
`Illuminate\Support\Facades\Http::fake()`, never a real network call.

Plus: `tests/Unit/Support/SafeLogTest.php` (redaction), `tests/Feature/Support/SecureStorageTest.php`
(the fallback genuinely encrypts, verified by reading the raw session
value directly — not just round-tripping through the same class),
`tests/Feature/Stores/{AuthTokenStoreTest,NetworkStatusStoreTest}.php`,
`tests/Feature/Actions/Api/CheckApiHealthActionTest.php`, and
`tests/Feature/Pages/ErrorAndOfflineScreensTest.php`. 45 new tests total;
full suite passes.
