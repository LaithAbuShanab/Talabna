# Project State

Last updated: 2026-07-02 — initial scaffolding, local MySQL switch + GitHub push,
unified quality standards, the full restaurant domain database schema,
realistic local dev/demo seed data, the cart-pricing domain layer, the
order-creation use case (checkout), the centralized order status
transition system, the versioned Sanctum-based auth/account API, the
public menu/catalog API, the protected customer order endpoints, then the
professional Filament v5 admin panel (roles, policies, audit trail).

## Filament v5 admin panel (this task)

`restaurant-backend` only. Full detail — the role matrix, every policy,
navigation groups, audit trail wiring, and the "why not a permissions
package" reasoning — is in the new **`docs/ADMIN_PANEL.md`**; this section
is a pointer/summary plus the real bugs this task caught.

- **Roles**: `App\Enums\UserRole`'s old single `admin` case split into
  five — `super_admin`, `manager`, `kitchen`, `cashier`, `support` (plus
  the pre-existing `customer`, still the only non-admin role —
  `isAdmin()`). New `users.is_active` column (default `true`) lets an
  admin account be deactivated without deleting it.
  `canAccessPanel()` now requires `role->isAdmin() && is_active`.
  `AdminUserSeeder` now creates one demo account per admin role
  (`admin@`/`manager@`/`kitchen@`/`cashier@`/`support@example.com`, same
  dev-only `password` placeholder).
- **Fine-grained policies, not button-hiding**: `OrderPolicy`'s three
  admin abilities re-tiered from "any admin" to a real matrix (`manage`:
  super_admin/manager/kitchen; `cancelAtReadyStage`: super_admin/manager;
  `cancelAtOutForDeliveryStage`: super_admin only). Three new policies —
  `UserPolicy` (managing admin accounts: super_admin only for
  create/update/delete, manager can view; never delete yourself),
  `RestaurantSettingPolicy`, `AdminActivityLogPolicy` (read-only,
  unconditionally denies create/update/delete) — all auto-discovered by
  Laravel's naming convention, all enforced server-side and proven so over
  real HTTP (403s asserted directly, not button visibility).
- **Decision, explicitly checked rather than assumed**: no permissions
  package added. `bezhansalleh/filament-shield` (wrapping
  `spatie/laravel-permission`) was checked against the real Packagist
  registry and **does** already declare Filament v5 support — so this
  wasn't a compatibility rejection. Skipped because the role set is fixed
  and small (no admin-configurable custom roles requested), and adopting
  it would mean replacing the single `role` enum column already used
  directly by `CartPricingService`/`OrderStatusTransitionService`/every
  customer API endpoint — a much bigger, riskier change than "prepare the
  panel." Plain Policies give the same enforcement guarantee for this
  fixed role set with zero new tables/dependencies.
- **Navigation groups**: new `App\Filament\Support\NavigationGroup` enum
  (9 cases: Dashboard/Orders/Menu/Customers/Promotions/Delivery/Reports/
  Settings/Administration), passed straight to `Panel::navigationGroups()`
  via Filament v5's native enum-class-string support
  (`NavigationGroup::fromEnum()`). Only Administration (`UserResource`,
  `AdminActivityLogResource`) and Settings (`ManageRestaurantSettings`)
  have Resources/Pages yet — full CRUD for Orders/Menu/Customers/
  Promotions/Delivery/Reports is explicitly out of scope for this task
  (panel infrastructure + a proof of the RBAC, not "build every domain
  resource") and remains future work, same as already flagged in this
  file's own backlog.
- **Branding/RTL/profile/notifications, all native Filament v5 APIs, no
  custom pages beyond what the task needed**: `->brandName()`/
  `->brandLogo()` are Closures reading `RestaurantSetting::current()`
  (new `logo_path` column), so editing them on the new Settings page
  updates the panel immediately. RTL/Arabic needed **zero new code** —
  Filament ships Arabic translations for every sub-package and derives
  `dir="rtl"` from the `ar` locale's own translation string, and
  `APP_LOCALE=ar` was already this project's default from an earlier task.
  `->profile(isSimple: false)` uses Filament's **built-in**
  `Filament\Auth\Pages\EditProfile` (already has name/email/password/
  current-password) — no custom profile page was written.
  `->databaseNotifications()` enabled (new `notifications` table
  migration — none existed before, nothing had sent one yet).
- **New, deliberately simple audit trail**: `admin_activity_logs`
  (append-only, same enforcement pattern as the existing
  `OrderStatusHistory`), `App\Services\AdminActivityLogger`, wired into
  three curated call sites — staff-made order status changes
  (`App\Listeners\LogAdminOrderStatusChange` on the existing
  `OrderStatusChanged` event, skipping customer/null-actor transitions),
  admin account create/update(role or is_active only)/delete, and
  settings updates. Viewable read-only at Administration → Activity Log.
- **Three real bugs found by testing, not by reading the code**:
  1. `NavigationGroup` originally implemented `HasIcon` as well as
     `HasLabel`; combined with every Resource/Page also setting its own
     icon, Filament hard-refuses this ("group or its items can have icons,
     but not both") — a `500` on every page in an affected group. Caught
     by an HTTP-level probe (`actingAs($admin)->get('/admin')`) before the
     real test suite was even written. Fixed by dropping `HasIcon` from
     the group enum.
  2. `role`/`is_active` are excluded from `User`'s `#[Fillable(...)]` (the
     existing privilege-escalation guard) — so Filament's default
     `CreateRecord`/`EditRecord` save methods (`new Model($data)` /
     `$record->update($data)`, both mass assignment) would have silently
     dropped both fields. Same failure shape as the `AuthController::register()`
     bug found in the earlier auth-API task; applied that lesson
     proactively via `handleRecordCreation()`/`handleRecordUpdate()`
     overrides using `forceFill()`, then verified with Livewire component
     tests that actually fill the form and call `create`/`save` (not just
     "the page loads").
  3. The already-seeded `admin@example.com` row (real local MySQL data)
     was still `role = 'admin'` — a value no longer in the enum, so every
     read would throw a `ValueError`. Fixed with a dedicated data migration
     (`migrate_legacy_admin_role_to_super_admin`, reversible), run and
     verified against the real database before writing any tests.
- **Tests**: `tests/Feature/Filament/{PanelAccessTest, UserResourceAccessTest,
  UserResourceCrudTest, RestaurantSettingsPageTest,
  AdminActivityLogResourceAccessTest}`, `tests/Unit/Policies/{UserPolicyTest,
  RestaurantSettingPolicyTest, AdminActivityLogPolicyTest}` (+ the existing
  `OrderPolicyTest` extended for the new matrix),
  `tests/Feature/Services/AdminActivityLoggerTest`,
  `tests/Feature/Listeners/LogAdminOrderStatusChangeTest` — 42 new tests:
  every admin role logging in, customer/deactivated-admin exclusion, RTL/
  Arabic/branding rendering, every Resource/Page's authorization matrix
  over real HTTP, the mass-assignment fix specifically, self-delete
  prevention, and the audit trail recording (or correctly not recording)
  per wired event.
- Full backend suite: **341 tests / 927 assertions** (up from 299/819),
  Pint clean (one pre-existing generated file missing
  `declare(strict_types=1)`, auto-fixed), stable across 3 repeated runs,
  verified against the real local MySQL `Talabna` database end-to-end —
  all 5 demo accounts' `canAccessPanel()` confirmed live via `tinker`
  after the legacy-role data migration ran.

## Protected customer order endpoints (previous task)

`restaurant-backend` only. Full endpoint-by-endpoint detail is in the new
**`docs/API_ORDERS.md`**; this section is a pointer/summary.

- **New endpoints, all under `/api/v1/orders`, `auth:sanctum`, scoped to
  the caller's own orders**: `GET /` (paginated, `status` filter, newest
  first, lean payload), `POST /` (create — requires an `Idempotency-Key`
  header), `GET /{order}` (full detail: status timeline, payment status,
  `expected_delivery_at`, `can_be_cancelled`, embedded `review`),
  `GET /{order}/timeline` (status history only), `POST /{order}/cancel`
  (per the existing customer-cancellation policy), `POST
  /{order}/reorder-preview` (recomputes a past order's items at *current*
  prices via `CartPricingService` — no order is created), `POST
  /{order}/review` (new — see below).
- **Nothing new was built for order creation/cancellation/pricing
  business logic** — `App\Actions\CreateOrderAction`,
  `App\Services\{CartPricingService, OrderStatusTransitionService}`, and
  `App\Policies\OrderPolicy` already existed from earlier tasks and were
  simply wired to routes for the first time. Added one new ability,
  `OrderPolicy::view()` (bare ownership), kept deliberately separate from
  `cancelAsCustomer()` so "not your order" (`403`) and "your order, but
  not cancellable right now" (`403` with a specific `errors.code`, see
  below) are never conflated into the same generic error.
- **`OrderCreationException` and `OrderStatusTransitionException` wired
  into `bootstrap/app.php`'s envelope for the first time** — both existed
  since earlier tasks but had never been reachable over HTTP (no route
  had thrown them yet). `OrderStatusTransitionException`'s two
  `unauthorized_*` codes render as `403` (a permission problem); every
  other code renders as `422` (a state/input problem) — a distinction
  `CartPricingException`/`OrderCreationException` didn't need, since none
  of their codes are permission-related.
- **New review/rating schema** (didn't exist before this task, as
  anticipated by the request): `order_reviews` (one row per order, unique
  on `order_id`, `rating` 1–5, optional `comment`), `App\Models\OrderReview`,
  `App\Exceptions\OrderReviewException` (`order_not_delivered`,
  `already_reviewed` — the latter also enforced by the DB unique
  constraint as defense in depth against a concurrent-submission race).
  Embedded in `OrderResource.review`, `null` until one exists.
- **Idempotency**: the `Idempotency-Key` HTTP header is merged into the
  Form Request's validated input in `prepareForValidation()`
  (`CreateOrderRequest`) so a missing key fails validation exactly like a
  missing body field, rather than needing a separate controller-level
  check. The underlying (`user_id`, `idempotency_key`) uniqueness
  guarantee already existed (`CreateOrderAction`, from the checkout task) —
  this task's job was purely exposing it as a header at the HTTP layer.
- **Reorder-preview reuses `CartPricingService` exactly** — rebuilds the
  same `CartPricingRequestData` `POST /cart/preview` takes from the old
  order's `items`/`options` (same delivery type/zone; a *new* optional
  coupon can be applied, the original coupon is never silently reapplied),
  so a reorder preview can never drift from what `/cart/preview` would
  compute for the same inputs. Items whose `product_id` is `null` (product
  hard-deleted since) are dropped and listed in `unavailable_items`; any
  other pricing failure among the surviving items fails the whole preview
  with the same `CartPricingException` `/cart/preview` would raise —
  deliberately not a new partial-recovery scheme.
- **Administrative-field hiding**: `OrderStatusHistoryResource` already
  only exposed `{status, note, created_at}` (no `changed_by_user_id`) from
  the earlier order-status-transition task — reused as-is for the new
  `timeline` endpoint, satisfying this task's "hide internal
  administrative info" requirement with no changes needed.
- **Tests**: `tests/Feature/Api/V1/Order/{OrderCreationTest,
  OrderListTest, OrderShowTest, OrderCancelTest, OrderReorderPreviewTest,
  OrderReviewTest}`, 45 tests — idempotency (same key + different body
  still returns the original order untouched; a different key creates a
  second one), every validation rule, cross-user ownership on all five
  order-scoped actions (403, and the underlying row provably unchanged),
  newest-first ordering, the status filter, pagination, the list payload
  staying lean (no `items` key at all) vs. the full detail shape, the
  customer-cancellable window (`pending`/`accepted` succeed, `preparing`+
  gets `403 unauthorized_transition`, terminal states get
  `422 terminal_state`), reorder-preview never creating a row and pulling
  *current* prices instead of the order's original snapshot, deleted-item
  flagging, review gating (must be delivered, exactly once, 1–5 bounds),
  and the review appearing embedded in order detail afterward.
- Full backend suite: **294 tests / 795 assertions** (up from 249/671),
  Pint clean with zero fixes needed, stable across 3 repeated runs,
  verified against the real local MySQL `Talabna` database end-to-end via
  `curl` — register → create order (with and without the idempotency
  header, same key twice, different key) → list/filter → show → timeline
  → cross-user 403s on show/cancel/timeline → cancel → re-cancel rejected
  (terminal state) → reorder-preview (confirmed no new order row) →
  review rejected pre-delivery → marked delivered via `tinker` → review
  accepted → duplicate review rejected → review visible embedded in
  the order.

## Public menu & catalog API (previous task)

`restaurant-backend` only. Full endpoint-by-endpoint detail is in the new
**`docs/API_MENU.md`**; this section is a pointer/summary plus the real
bugs this task caught (both cache-related, both only reproducible against
the real `database` cache store — the test suite's `array` store masked
them completely, see below).

- **New public endpoints, all under `/api/v1`, no auth required**: `GET
  restaurant` / `restaurant/hours` / `restaurant/is-open`, `GET
  categories`, `GET products` (filters: `category_id`, `search` — doubles
  as the "search products" endpoint — `page`, `per_page`), `GET
  products/{id}`, `GET delivery-zones`, `POST delivery-zones/check` (by
  `zone_id` or `latitude`/`longitude`, Haversine radius match), `POST
  cart/preview` (also the "validate coupon" endpoint — pass `coupon_code`
  in the same request).
- **Bilingual content**: chose separate `name_ar`/`description_ar`
  nullable columns (new migration) over converting `name`/`description`
  to a JSON translations column, specifically so nothing outside this API
  had to change — `CartPricingService`, `CreateOrderAction`, and the
  order-snapshot columns all keep reading `name` as the same plain string
  as before. Applies to `Category`/`Product` (name+description) and
  `OptionGroup`/`OptionValue` (name only). API always returns
  `{en, ar}`, falling back to English when no Arabic translation exists.
  Seeders (`CategorySeeder`, `OptionSeeder`, `ProductSeeder`) updated with
  real Arabic translations for all demo data, re-seeded against the local
  MySQL database (idempotent `updateOrCreate`, so existing rows got the
  translations without a fresh migrate).
- **Prices**: every price everywhere in this API is
  `{amount_minor, formatted, currency}` (`App\Support\Money`) — `formatted`
  correctly divides by 3 decimals for JOD (this restaurant's currency),
  not the more common 2, via a small ISO-4217-minor-unit lookup table.
- **New**: `App\Services\MenuCacheService` (read-through cache, 1 hour
  TTL), `App\Observers\MenuCacheObserver` (invalidates on
  save/delete/restore of any of 8 menu-related models, registered in
  `AppServiceProvider::boot()` — fires regardless of what triggers the
  change, so it's already correct for whenever Filament Resources for
  these models are eventually built, with zero further changes needed),
  `App\Services\RestaurantAvailabilityService` (extracted out of
  `CreateOrderAction`'s private `isRestaurantOpen()` so `is-open` and real
  checkout can never drift apart — pure refactor, `CreateOrderActionTest`
  unchanged and still passing), `App\Support\{Money, Geo}`, and the
  `Menu`/`Cart` API Resource namespaces (`ProductListResource` vs.
  `ProductDetailResource` deliberately separate — list excludes
  description/options to keep the payload lean).
- **`CartPricingException` wired into `bootstrap/app.php` for the first
  time** (→ 422 with a stable `errors.code`): it existed since the
  cart-pricing task but had never been reachable over HTTP before now (no
  route threw it) — would have 500'd as a generic "Server Error." otherwise.
- **Two real bugs found by live `curl` testing against the real MySQL +
  `database`-cache-store environment, invisible to the test suite** (which
  runs `CACHE_STORE=array`, an in-memory store that doesn't serialize
  values at all, so neither bug could ever reproduce there):
  1. Laravel's `database` cache store defaults `serializable_classes` to
     `false` (a deliberate security hardening against PHP-object-injection
     / gadget-chain attacks introduced in recent Laravel versions) —
     unserializing any cached PHP *object* silently returns a useless
     `__PHP_Incomplete_Class` instead of throwing. `MenuCacheService`
     originally cached raw Eloquent models/Collections directly; every
     cache *hit* (not the first, cache-populating request) broke
     immediately (`GET /restaurant/is-open` was the first endpoint that
     actually hit a warm cache during manual testing, since it reads the
     same settings `GET /restaurant` had just cached). Rather than weaken
     that security default, fixed by caching only plain arrays — flat
     models (categories, delivery zones, business hours, restaurant
     settings) via `->toArray()` + `Model::hydrate()`/`newFromBuilder()`
     reconstruction; products (too deep a relation tree to reconstitute
     cleanly) via caching the *already-resolved API Resource arrays*
     directly instead.
  2. Fixing bug 1 surfaced a second, subtler one: `JsonResource::resolve()`
     only converts the *outer* resource to an array — a nested resource
     returned as a plain array *value* (e.g. `ProductDetailResource`'s
     `category` or `option_groups`) is left as a live, uncached-safe
     Resource object. Invisible in a normal HTTP response because
     `response()->json()` calls `json_encode()`, which recurses into every
     `JsonSerializable` value it finds at any depth — but caching
     `resolve()`'s output directly skips that recursion, so the exact same
     `__PHP_Incomplete_Class` symptom reappeared one level deeper
     (`GET /products/{id}`, cold *and* warm, both broken). Fixed with a
     `deepResolve()` helper that forces the same recursive expansion via a
     `json_encode()`/`json_decode()` round trip before caching.
- **Tests**: `tests/Feature/Api/V1/Menu/{RestaurantTest, CategoryTest,
  ProductTest, DeliveryZoneTest, CartPreviewTest, MenuCacheTest}` — every
  filter/pagination/search case, the bilingual fallback-to-English
  behavior, inactive-category/unavailable-product exclusion on both list
  and detail, nested option groups/values (including an inactive value
  correctly excluded), coupon validation (valid/invalid/expired) inside
  cart preview, delivery-zone checks (by id and by coordinate, inside and
  outside radius), all three independent `is-open` conditions, and —
  explicitly, in `MenuCacheTest` — that editing each underlying model
  immediately invalidates that endpoint's cache rather than serving stale
  data. A pre-existing latent bug in `DeliveryZoneFactory` also surfaced
  here purely from added test volume: `fake()->unique()->citySuffix()`'s
  pool is small enough (`name` isn't even a unique DB column) that ~300
  factory calls across the whole suite exhausted it
  ("Maximum retries of 10000 reached"); fixed by dropping `unique()` in
  favor of appending a random number.
- Full backend suite: **249 tests / 671 assertions** (up from 206/537),
  Pint clean, stable across 3 repeated runs, verified against the real
  local MySQL `Talabna` database end-to-end — every endpoint live via
  `curl` (including confirming a cache *hit* returns byte-identical data
  to the cold read, and that editing a product/category/option value via
  `tinker` is reflected on the very next request, not after a TTL wait).

## Versioned auth & account API (previous task)

`restaurant-backend` only. Full endpoint-by-endpoint detail (request/response
shapes, rate limits, error codes) is in the new **`docs/API_AUTH.md`**; this
section is a pointer/summary plus the real bug this task caught.

- **Real versioning introduced**: `routes/api.php` is now a thin
  `Route::prefix('v1')->group(base_path('routes/api_v1.php'))` pointer; all
  route definitions (including the pre-existing `health`/`user` routes,
  moved for consistency) live in the new `routes/api_v1.php`. Updated
  `docs/API_CONVENTIONS.md`'s "Base URL & versioning" section to match
  (previously said to treat the unversioned API as v1 implicitly — this task
  is that promised introduction of real versioning).
- **New endpoints, all under `/api/v1`**: `POST auth/register`, `POST
  auth/login`, `POST auth/logout`, `POST auth/logout-all-devices`, `POST
  auth/forgot-password`, `POST auth/reset-password`, `GET`/`PUT profile`,
  `PUT profile/password`, `GET`/`POST addresses`, `PUT`/`DELETE
  addresses/{address}`, `POST addresses/{address}/default` —
  `App\Http\Controllers\Api\V1\{AuthController, PasswordResetController,
  ProfileController, CustomerAddressController}`.
- **New**: Form Requests under `App\Http\Requests\Api\V1\{Auth,Profile,Address}`,
  `App\Http\Resources\{UserResource, CustomerAddressResource}`,
  `App\Policies\CustomerAddressPolicy` (view/update/delete, ownership by
  `user_id`), `App\Notifications\ApiResetPasswordNotification` (overrides
  `User::sendPasswordResetNotification()` to email the raw reset token
  instead of Laravel's default web-URL link — this backend has no
  reset-password web page, and a numeric OTP was explicitly out of scope),
  `lang/{en,ar}/address.php`, new keys in the existing
  `lang/{en,ar}/{auth,passwords,validation}.php`.
- **Security properties, by construction**: `role` is never settable through
  register/update-profile (not in the Form Request rules, and not
  mass-assignable on `User` regardless — verified by a dedicated test).
  Forgot-password **always returns the identical response** whether or not
  the email belongs to a real account (`Password::sendResetLink()`'s return
  value is deliberately ignored) — cannot be used to enumerate accounts.
  Every address operation checks ownership via `CustomerAddressPolicy`
  (`403` for another user's address, verified for update/delete/set-default).
  Deleting an address linked to a past order needs no new blocking logic —
  the schema task's existing snapshot design (`orders.delivery_address_*`
  columns + `customer_address_id` `nullOnDelete()`) already guarantees the
  order's recorded delivery details survive untouched; proven with a
  dedicated test rather than added as new code.
- **Rate limiting**: named limiters `login` (5/minute) and `forgot-password`
  (3/minute) in `AppServiceProvider::boot()`, both keyed by `email + IP` (not
  IP alone, so a shared NAT/proxy can't lock out every account behind it; not
  email alone, so an attacker can't lock a victim out from anywhere) —
  applied via `throttle:login`/`throttle:forgot-password` middleware.
- **Token semantics**: tokens are named after the client-supplied
  `device_name`. `logout` revokes only the current request's token;
  `logout-all-devices` and `reset-password` revoke every token; changing the
  password revokes every *other* token but preserves the session used to
  make the change.
- **A real bug found while smoke-testing, not by inspection**:
  `ProfileController::changePassword()` called
  `$user->currentAccessToken()->id` to exclude the current session's token
  from revocation. `currentAccessToken()` returns a `Laravel\Sanctum\
  TransientToken` (no database `id`) whenever a request authenticates via
  the `web` session guard instead of a real bearer token — which is exactly
  what happens under `actingAs()` in tests, and could in principle happen
  for any request carrying a `web` session (Sanctum's guard checks the
  configured session guard *before* falling back to bearer-token lookup;
  see `config/sanctum.php`). This crashed with a 500
  (`Undefined property: TransientToken::$id`). Fixed to check `instanceof
  PersonalAccessToken` first and, if not, revoke every real token instead
  (there's no specific device token to preserve when the request wasn't
  authenticated by one). Caught by the automated test suite, not manual
  `curl` smoke-testing — manual testing only ever used real bearer tokens,
  which never hit this path.
- **A test-harness-only quirk, not a production bug**: tests that simulate
  two different devices' tokens in *sequential* HTTP calls within one test
  method (e.g. "device A logs out, device B is still logged in") saw the
  first-resolved user staying authenticated on later calls regardless of
  the token sent. Root cause: Sanctum's guard (`RequestGuard`) memoizes the
  resolved user for the guard instance's lifetime, and Laravel's test
  client reuses the same application/container (and therefore the same
  cached guard) across multiple simulated requests within a single test —
  unlike production, where every real HTTP request gets a fresh guard.
  Added `TestCase::forgetAuthGuards()` (calls `Auth::forgetGuards()`) and
  call it between same-test requests that switch which token/user is
  authenticating.
- **Tests**: `tests/Feature/Api/V1/{AuthTest, PasswordResetTest,
  ProfileTest, CustomerAddressTest, RateLimitingTest}`, 39 tests covering
  register (including the role-escalation guard and duplicate-email/
  password-confirmation validation), login (success, wrong password, unknown
  email, device-named tokens), single-device vs. all-devices logout,
  protected-route authentication, forgot-password's anti-enumeration
  guarantee (byte-identical responses) and that it actually creates a
  reset-token row, reset-password (valid/invalid token, token revocation),
  profile view/update (including the cross-user unique-email check),
  change-password (correct/wrong current password, other-devices-revoked/
  current-session-preserved), address CRUD, first-address-auto-default,
  default-exclusivity, cross-user ownership on update/delete/set-default
  (403), the address-deletion/order-snapshot guarantee, and rate limiting on
  both `login` and `forgot-password`. Also updated the pre-existing
  `HealthEndpointTest`/`ApiResponseFormatTest` to the new `/api/v1/...`
  paths.
- Full backend suite: **206 tests / 537 assertions** (up from 172/437),
  Pint clean, verified against the real local MySQL `Talabna` database too —
  a live server boot walkthrough of every endpoint (register → login →
  profile → update → change-password → forgot-password → reset-password →
  address CRUD → set-default → delete → logout/logout-all), including the
  login and forgot-password rate limits actually returning `429` after 5/3
  attempts, before the automated tests were written.

## Order status transition system (previous task)

`restaurant-backend` only. Full detail — the complete lifecycle graph, who
can do what, and every rule enforced — is in the new
**`docs/ORDER_LIFECYCLE.md`**; this section is a pointer/summary plus a real
gap this task fixed in earlier work.

- **New**: `App\Services\OrderStatusTransitionService` (the single place
  `orders.status` is ever changed after creation),
  `App\DataTransferObjects\Order\TransitionOrderStatusData`,
  `App\Policies\OrderPolicy` (four named abilities: `cancelAsCustomer`,
  `manage`, `cancelAtReadyStage`, `cancelAtOutForDeliveryStage` — kept
  separate even though the latter three currently all just check
  `role === admin`, so tightening any one later is a one-method change),
  `App\Exceptions\OrderStatusTransitionException` (new keys added to the
  existing `lang/{en,ar}/order.php`, not a new lang file),
  `App\Events\OrderStatusChanged` +
  `App\Listeners\SendOrderStatusChangedNotification` (queued, reuses the
  same `App\Contracts\PushNotifier` seam from order creation).
- **A real gap in earlier work, found while re-deriving the transition
  matrix from this task's spec**: `OrderStatus`'s transition graph
  (written during the schema task) disallowed `out_for_delivery → cancelled`
  entirely. This task's spec explicitly requires that transition to be
  *allowed*, just gated behind "very special permission" — a fine
  distinction the original enum design collapsed into "not allowed at all."
  Fixed by adding the edge to `OrderStatus::transitionMap()` and updating
  the one existing test that had asserted the old (wrong) behavior
  (`OrderStatusTest::test_out_for_delivery_cannot_be_cancelled`, renamed and
  corrected).
- **Schema additions**: `order_status_histories.from_status` (nullable —
  explicit old-status-per-row, rather than only implying it from the
  previous row) and `.metadata` (nullable JSON, optional free-form
  context) — both directly required by this task's "record the old status
  / optional metadata" requirements, which the original append-only history
  table didn't carry.
- **Enforcement, in order**: row lock (re-fetch with `lockForUpdate()`
  inside `DB::transaction()`, so validation always sees the current DB
  state, never a stale in-memory `Order`) → terminal-state check → graph
  check → pickup-only check (`ready → delivered` only for `delivery_type =
  pickup`) → authorization (`OrderPolicy`) → required-reason check
  (`rejected`/`cancelled`) → persist `Order` + one
  `order_status_histories` row, all in the same transaction →
  `OrderStatusChanged` dispatched only after the transaction returns
  successfully.
- **Customer surface is intentionally tiny**: a customer can only ever
  cancel their *own* order, and only while it's `pending` or `accepted`
  (`OrderStatus::isCustomerCancellable()`) — every forward transition
  (accept/reject/prepare/ready/dispatch/deliver) is admin-only, verified by
  a dedicated test per case, not just one generic "customers can't do
  admin stuff" assertion.
- **Tests**: `tests/Unit/Policies/OrderPolicyTest.php` (7 tests, genuinely
  DB-free — `OrderPolicy` only reads attributes already on the in-memory
  models it's given, so plain unsaved `new User()`/`new Order()` instances
  are enough) and `tests/Feature/Services/OrderStatusTransitionServiceTest.php`
  (46 tests): every one of the 12 allowed transitions, 13 structurally
  invalid ones (data provider), all 3 terminal states rejecting any further
  transition, the pickup-only rule, reason enforcement for both
  reject/cancel, the full customer-authorization surface (including
  same-order-different-user and post-preparing-window-closed cases), the
  two special-permission gates independently proven via a `Mockery`-mocked
  `OrderPolicy` (so the test verifies the *service* correctly consults the
  gate, not just today's admin-only implementation of it), a `null` actor
  bypassing authorization, estimated-preparation-minutes vs. an explicit
  `expectedDeliveryAt` taking precedence, `from_status`/`metadata`/actor
  recording, a stale-in-memory-copy test proving the row-lock re-fetch is
  real (mutates the DB directly behind the service's back, confirms the
  service still sees it), a transaction-rollback test (same
  `ModelClass::creating()`-throws-and-`flushEventListeners()`-in-`finally`
  technique as `CreateOrderActionTest`), and event dispatch only on
  success.
- Also updated the existing `tests/Unit/Enums/OrderStatusTest.php` for the
  `out_for_delivery → cancelled` fix above, and added a test for the new
  `isCustomerCancellable()` enum method.
- Full backend suite: **172 tests / 437 assertions**, Pint clean, stable
  across 3 repeated runs, verified against the real local MySQL `Talabna`
  database too (fresh migrate+seed, then a full live walkthrough via
  `tinker`: accept-with-estimated-time, unauthorized-customer rejection,
  missing-reason rejection, terminal-state protection, the pickup-only
  rule, the ready-stage special-permission cancellation, and the
  customer-cancellation window opening/closing exactly at "preparing"
  — all before a single automated test was written).

## Order creation use case (previous task)

## Order creation use case (this task)

`restaurant-backend` only. Full detail is in the new "Order creation"
section of **`docs/DATABASE_SCHEMA.md`** — this is a pointer/summary plus
the real bugs this task caught.

- **New**: `App\DataTransferObjects\Order\CreateOrderData`,
  `App\Actions\CreateOrderAction`, `App\Services\OrderNumberGenerator`
  (extracted out of `App\Models\Order`, which previously had the
  order-number logic as a static method — now the model just calls
  `app(OrderNumberGenerator::class)->generate()` from its `creating` event),
  `App\Events\OrderCreated`, `App\Listeners\SendOrderCreatedNotification`
  (queued), `App\Contracts\PushNotifier` + its default
  `App\Notifications\Push\LogPushNotifier` implementation (bound in
  `AppServiceProvider` — no real push provider exists yet, swapping one in
  is a one-line binding change), `App\Exceptions\OrderCreationException` +
  `lang/{en,ar}/order.php`, and `App\Http\Resources\{OrderResource,
  OrderItemResource, OrderItemOptionResource, PaymentResource,
  OrderStatusHistoryResource}`.
- **Schema additions**: `orders.idempotency_key` (nullable,
  `unique(user_id, idempotency_key)`) and
  `restaurant_settings.allows_scheduled_orders` (schema readiness only —
  see docs, scheduled ordering is explicitly not implemented).
  `App\DataTransferObjects\Cart\CartPricingResultData` gained
  `appliedCouponId` (alongside the existing `appliedCouponCode`) so
  `CreateOrderAction` can record a `CouponUsage` without a redundant
  re-query or a race window.
- **`CreateOrderAction::execute()` flow**: idempotency check (return the
  existing order unchanged if `(user_id, idempotency_key)` already exists,
  including a fallback that catches `UniqueConstraintViolationException`
  for the genuine-concurrent-request case) → restaurant open/closed check
  (`RestaurantSetting.is_accepting_orders` + today's `business_hours`
  row) → delivery address resolution (required + must belong to the
  ordering user for `delivery`, ignored for `pickup`) →
  `CartPricingService::price()` for every validation/amount → one DB
  transaction creating `Order` → `OrderItem`/`OrderItemOption` → the first
  `OrderStatusHistory` (`pending`) → `CouponUsage` if a coupon applied →
  `Payment` (`pending`) → `OrderCreated` dispatched only after the
  transaction returns successfully.
- **Security property, by construction**: `CreateOrderData`/`CartItemInputData`
  carry no price field anywhere — proven with a test that changes a
  product's price mid-test and reorders, asserting the new price is what
  gets stored.
- **Two real bugs found and fixed while testing, not by inspection**:
  1. A `*/` sequence *inside* a `/* ... */` doc comment
     (`lang/{en,ar}/order.php`, written as "lang/\*/cart.php") prematurely
     closed the comment block, turning the rest of the file into invalid
     PHP — a `ParseError` that only surfaced the moment
     `OrderCreationException` actually tried to translate a message (i.e.
     the very first time any order-creation failure path was exercised).
     Fixed by rewording to "lang/{locale}/cart.php". This is exactly the
     kind of thing `php -l`/Pint don't catch on their own but a real test
     invocation does.
  2. That same bug briefly looked like a shell-quoting artifact during
     manual `tinker` smoke-testing (the error message — "unexpected
     identifier 'instead'" — reads like garbled shell escaping), which
     delayed diagnosis. Root-caused by re-running through PHPUnit's
     `--log-junit` output, which preserved the full exception stack trace
     (`.../lang/ar/order.php:14`) instead of a summarized one-liner —
     worth remembering next time a "syntax error" appears in a test result
     that doesn't match anything in the file being tested: check what
     `trans()`/`__()` calls might be loading elsewhere.
- **Tests**: `tests/Feature/Actions/CreateOrderActionTest.php`, **21
  tests** covering every explicitly requested case (delivery order, pickup
  order, idempotency, restaurant closed, product became unavailable,
  transaction failure, client prices rejected, status-history/payment
  creation) plus: pickup never requiring an address, delivery rejecting a
  missing or other-user's address, different idempotency keys creating
  different orders, the same key being reusable across different users,
  closure via business hours specifically (not just the
  `is_accepting_orders` flag), an empty cart (delegated to
  `CartPricingService`), snapshotting surviving a product rename mid-test,
  coupon usage recording, `OrderCreated` dispatching only on success (and
  specifically *not* dispatching on failure, via `Event::fake()`), and the
  `OrderResource` JSON shape. The transaction-rollback test injects a
  failure via a real `OrderItem::creating()` listener (not a mock) and
  explicitly flushes it in a `finally` block so it can't leak into other
  tests. Ran 3x in a row to confirm no flakiness.
- Full backend suite: **118 tests / 365 assertions**, Pint clean, verified
  end-to-end against the real local MySQL `Talabna` database too (a full
  checkout with a coupon, a delivery zone, and a required product option,
  computed correctly: 600+400 Large × 2 = 2000 subtotal, 200 discount via
  `WELCOME10`, 300 delivery fee, 2100 total).

## Cart pricing domain layer (previous task)

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

- Build the first real API endpoints (menu browsing, cart pricing preview,
  checkout, order status/cancel) following `docs/API_CONVENTIONS.md` — Form
  Requests around `CreateOrderData`/`TransitionOrderStatusData`, and
  controllers that call `CreateOrderAction`/`OrderStatusTransitionService`
  and wrap the result in `OrderResource` via `ApiResponse::success()`.
  `CartPricingService`, `CreateOrderAction`, and
  `OrderStatusTransitionService` are all fully built and tested — this is
  "wire them to routes," not new business logic.
- A Filament Resource/action for admins to move an order through the
  lifecycle from `/admin` — `OrderStatusTransitionService` is ready to be
  called from a Filament action button; nothing calls it from the admin
  panel yet.
- Add `orders.tax_amount` and have `CreateOrderAction` persist it once tax
  needs to survive past the pricing-preview stage.
- Refunds: `payments.status` already supports `refunded`/`partially_refunded`
  but nothing sets them yet.
- Wire an actual HTTP client/service in `restaurant-customer-app` that reads
  `RESTAURANT_BACKEND_URL` and calls the backend API.
- Decide and implement the real `NATIVEPHP_APP_ID` reverse-DNS identifier.
- Install `php8.4-sqlite3` on this host (requires sudo) if the team wants to
  standardize on PHP 8.4.
- Filament Resources for the new models (currently there's a schema/domain
  layer but no admin UI yet to manage products/categories/orders through
  `/admin`).
