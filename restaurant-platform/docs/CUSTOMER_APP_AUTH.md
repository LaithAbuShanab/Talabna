# Customer app authentication

`restaurant-customer-app` only. This documents the app's **only** auth
system: 9 screens (Splash, Onboarding, Login, Register, Forgot password,
Reset password, Logout, Profile, Change password) built directly against
`restaurant-backend`'s Sanctum REST API via the existing
`App\Services\Api\ApiClient` (see `docs/CUSTOMER_APP_API_CLIENT.md` for the
client itself). Real customer identity lives entirely on the backend —
there is no local session/cookie auth in this app.

## Fortify was removed, not reused

The Livewire starter kit ships Laravel Fortify (local session-based auth
against this app's own local `users` table: login, register, 2FA,
passkeys, email verification). It was confirmed to be completely unused
starter-kit scaffolding — zero customization, zero real user rows — before
this task started. Given that real customer auth was always going to call
`restaurant-backend`'s API (per the architecture set up in the previous
task), keeping two parallel, unconnected auth stacks in the same app would
be confusing and unmaintainable. The user was asked explicitly and chose
**full replacement**: Fortify (package, provider, actions, config, 2FA/
passkey columns, dependent views/tests) was removed entirely, and its
routes (`/login`, `/register`, `/settings/profile`, etc.) were rewired to
the new API-backed screens.

The local `users` table and `App\Models\User` still exist only because
Laravel's framework internals expect a default auth provider/model
(`config/auth.php`). No local row is ever created for a real customer, and
none of the new screens use Laravel's `Auth` facade or session guard.

## Session model: a bearer token, not a session

There is no server-side session concept for "logged in". The single source
of truth is whether `App\Stores\AuthTokenStore` holds a Sanctum token
(persisted via the pre-existing `App\Support\SecureStorage` — Keychain/
Keystore on-device, `Crypt`-encrypted session fallback only in plain-browser
local dev). Every authenticated screen (`/dashboard`, `/logout`,
`/settings/profile`, `/settings/change-password`) is gated by
`App\Http\Middleware\EnsureBackendSessionExists` (alias `backend.auth`),
which is a cheap local-only check (`AuthTokenStore::hasToken()`) — it does
**not** attempt to validate the token against the backend on every page
load. That validation happens once, at app open, on the Splash screen.

## Session restoration on app open (`/`, Splash)

`استعادة جلسة المستخدم عند فتح التطبيق`. `pages::splash`'s `mount()`:

1. No onboarding seen yet (`OnboardingStore::hasCompletedOnboarding()` is
   `false`) → redirect to `/onboarding`.
2. Onboarding done, but no stored token → redirect to `/login`.
3. A token is stored → call `FetchProfileAction::execute()` (`GET
   /profile`) to confirm it's still valid:
   - Success → redirect to `/dashboard`.
   - `ApiUnauthorizedException` (a **confirmed** 401 — see below; the
     token has already been cleared by `ApiClient` itself by this point) →
     redirect to `/login`.
   - `ApiConnectivityException` (offline or the request timed out) →
     redirect to `/dashboard` anyway. This is deliberate: a transient
     network failure must never be treated as "logged out". The user
     keeps their locally-cached session and can retry the request that
     actually needs the network from wherever it happens next.
   - Any other `ApiException` (e.g. an unexpected 5xx) → redirect to
     `/error`, the existing generic error screen.

All 6 branches are covered by `tests/Feature/Pages/SplashTest.php`.

## Confirmed-401 vs. 403 vs. transient failure

`حذف token عند 401 المؤكد` + `عدم حذف الجلسة بسبب timeout عابر`. Both
requirements are satisfied by one precise rule, implemented in a single
place — `App\Services\Api\ApiClient::parseResponse()` — rather than
scattered across every screen that calls the API:

- **401** (an actual HTTP response telling us the token is invalid/expired)
  → `AuthTokenStore::forget()` is called immediately, before the
  `ApiUnauthorizedException` is even thrown. This is the only case that
  clears the token.
- **403** (a real response, valid token, forbidden for this specific
  resource — e.g. another customer's order) → the token is **never**
  cleared. A 403 is not evidence the token itself is bad.
- **Any connectivity failure** (offline, DNS failure, connection reset,
  timeout) is raised by `ApiClient` as `ApiConnectivityException` **before**
  a response is ever received, so it can never reach the 401-clearing code
  at all — there is no response to parse. A screen that catches
  `ApiConnectivityException` (Splash, Logout) treats it as "try again
  later", never as "log the user out".

Covered by `tests/Feature/Services/Api/ApiClientTest.php`
(`test_a_confirmed_401_clears_the_stored_token`,
`test_a_403_never_clears_the_stored_token`,
`test_a_transient_timeout_never_clears_the_stored_token`,
`test_being_offline_never_clears_the_stored_token`) and end-to-end against
a real, live `restaurant-backend` (an actually-expired/invalid token was
sent and a genuine 401 confirmed, with the local token cleared as a
result).

## Password reset: manual token entry, not a deep link

`Reset password إن كان deep link مدعومًا، وإلا وثّق المسار المناسب`. No
deep link is supported for this flow, confirmed two ways before designing
the screen:

- `docs/API_AUTH.md` documents that `App\Notifications\
  ApiResetPasswordNotification` (on `restaurant-backend`) deliberately
  emails the customer a **raw reset token**, not a clickable URL — the
  backend is API-only and has no web page to link to.
- `config/nativephp.php`'s `deeplink_scheme`/`deeplink_host` are unset in
  this app's `.env`.

Consequently, `pages::auth.reset-password` is a manual-entry form: email
(pre-filled from `?email=` if the customer arrived via a copy-pasted link
from some other client, otherwise blank), token, new password, and
confirmation — the customer copies the token out of the email themselves.
`POST /auth/reset-password` revokes **every** existing token for that user
on success, so any other signed-in device is logged out too; this app only
clears its own local token as a side effect of the customer then logging in
again.

## Error handling: local validation + server 422s in one place

`validation محلية، مع عرض أخطاء 422 القادمة من الخادم`. All 6 form-
submitting screens (Login, Register, Forgot password, Reset password,
Profile, Change password) mirror the backend's own validation rules
locally first (e.g. `Illuminate\Validation\Rules\Password::min(8)
->letters()->numbers()`, matching `restaurant-backend`'s own rule exactly)
for immediate feedback before any network round trip, then share one
trait, `App\Concerns\HandlesApiExceptions`, to handle whatever the backend
still rejects:

- `ApiValidationException` (422) → routed field-by-field into Livewire's
  native `addError()` bag, so `@error('field')` in the Blade template works
  identically whether the error came from local rules or the server.
- Anything else (`ApiRateLimitedException`, `ApiConnectivityException`, or
  any other `ApiException`) → a general `$generalError` string, shown as a
  banner rather than attached to a specific field.

## Loading states and double-tap prevention

`loading states` + `منع الضغط المتكرر`. Both are handled declaratively by
Livewire's client-side JS, with no server-side `$isSubmitting`-style
property anywhere: every submit button uses
`wire:loading.attr="disabled" wire:target="save"` (or whichever method),
plus a `wire:loading`/`wire:loading.remove` pair of `<span>`s to swap the
label for a "loading..." string while the request is in flight. Livewire
disables the button for the whole round trip purely based on
request-in-flight status, which is exactly "prevent a repeated tap" with
no additional code.

## Password visibility toggle

`إظهار وإخفاء كلمة المرور`. Every password field uses Flux's built-in
`viewable` attribute (`<flux:input type="password" viewable ... />`) — no
custom show/hide logic was written; Flux ships this natively.

## Device name at login/register

`تسجيل device name عند login`. `App\Support\DeviceNameResolver` reads
`Native\Mobile\Facades\Device::getInfo()` defensively (only `platform` is
documented/guaranteed; any other key such as `model` is read with a
null-safe fallback chain) and resolves to a human-readable device name,
falling back to `'Mobile Device'` if the payload can't be parsed at all, or
`'Web Browser'` if there's no native bridge (plain-browser local dev). Both
`RegisterAction` and `LoginAction` pass this resolved name as the
required `device_name` field to `POST /auth/register` and `POST
/auth/login` respectively — this is what the backend uses to name the
Sanctum token, so a customer can later see and revoke individual device
sessions from a future "manage devices" screen (not built — only "Logout"
singular was requested this task).

## Social login: not implemented

`عدم تنفيذ social login الآن`. Honored by simply never adding any
social-login button, route, or provider config anywhere in these 9
screens. No scaffolding, no disabled buttons, no placeholder config.

## Logout

`LogoutAction` calls `POST /auth/logout` (revokes only the current
device's token on the backend) inside a `try`, and **always** calls
`AuthTokenStore::forget()` in a `finally` block regardless of whether the
API call succeeded — "best-effort server revoke, guaranteed local logout".
This means a customer can always log out locally even while offline;
`tests/Feature/Pages/LogoutTest.php::test_confirming_still_logs_out_locally_even_if_the_server_call_fails`
covers this directly. `pages::logout` itself is a confirmation screen
(asks "are you sure?"), not an instant action triggered by just visiting
the route.

## Screens reference

| Screen | Route | Component | Auth required |
|---|---|---|---|
| Splash | `/` | `pages::splash` | no (decides where to send you) |
| Onboarding | `/onboarding` | `pages::onboarding` | no |
| Login | `/login` | `pages::auth.login` | no |
| Register | `/register` | `pages::auth.register` | no |
| Forgot password | `/forgot-password` | `pages::auth.forgot-password` | no |
| Reset password | `/reset-password` | `pages::auth.reset-password` | no |
| Logout | `/logout` | `pages::logout` | yes (`backend.auth`) |
| Profile | `/settings/profile` | `pages::settings.profile` | yes (`backend.auth`) |
| Change password | `/settings/change-password` | `pages::settings.change-password` | yes (`backend.auth`) |

All screens are mobile-first Blaze single-file Livewire components under
`resources/views/pages/`, rendered inside `layouts.auth.simple` via the
`#[Layout(...)]` attribute (required — wrapping a Livewire component's own
template directly in `<x-layouts::auth>` breaks Livewire's single-root-
element requirement, since that layout's own output has sibling top-level
nodes like `@persist('toast')`). All bilingual strings live in
`lang/{en,ar}/auth.php` under new nested keys (`onboarding`, `login`,
`register`, `forgot_password`, `reset_password`, `logout`, `profile`,
`change_password`); RTL is automatic via the existing
`dir="rtl"`/`app()->getLocale()` mechanism, no per-screen changes needed.

## Verification

- Unit/feature tests: `ApiClientTest` (+4 for the 401/403/transient
  distinction, 25 total), `AuthActionsTest` (13, covering all 8
  `App\Actions\Api\*` classes), `DeviceNameResolverTest` (4),
  `OnboardingStoreTest` (2), `EnsureBackendSessionExistsTest` (2), plus one
  Livewire test file per screen (Login 5, Register 5, ForgotPassword 3,
  ResetPassword 5, Splash 6, Onboarding 2, Logout 4, Profile 4,
  ChangePassword 4). Full suite: 109 passed.
- End-to-end against a real, live `restaurant-backend` (not
  `Http::fake()`), via `php artisan tinker`: genuinely registered a
  customer, fetched/updated their profile, changed their password, logged
  out, logged back in with the new password, triggered forgot-password,
  read the real emailed raw reset token out of the backend's log
  (`MAIL_MAILER=log`), completed a real password reset with it, and
  confirmed an invalid/expired token produces a genuine 401 that clears
  the local token. Test users created during this were cleaned up from
  the backend's database afterward.
