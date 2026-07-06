# Events, Listeners & Notifications

`restaurant-backend` reacts to the order/payment lifecycle through domain
Events, thin synchronous Listeners, and one queued Job that's the sole seam
to an external push provider. This document is the map of that structure;
see `docs/ORDER_LIFECYCLE.md` for the status-transition rules themselves.

## The event list

| Event | Dispatched by | Fires when |
|---|---|---|
| `OrderCreated` | `App\Actions\CreateOrderAction` | A new order is persisted |
| `OrderStatusChanged` | `App\Services\OrderStatusTransitionService` | Any status transition (generic — feeds the admin audit trail) |
| `OrderAccepted` | `OrderStatusTransitionService` | `→ accepted` |
| `OrderRejected` | `OrderStatusTransitionService` | `→ rejected` |
| `OrderPreparing` | `OrderStatusTransitionService` | `→ preparing` |
| `OrderReady` | `OrderStatusTransitionService` | `→ ready` |
| `OrderOutForDelivery` | `OrderStatusTransitionService` | `→ out_for_delivery` |
| `OrderDelivered` | `OrderStatusTransitionService` | `→ delivered` |
| `OrderCancelled` | `OrderStatusTransitionService` | `→ cancelled` |
| `PaymentStatusChanged` | `App\Services\PaymentStatusUpdateService` | A payment's status changes |

Every event above is dispatched **only after its owning transaction has
committed**, never from inside it — "عدم إرسال notification قبل نجاح
database transaction". This was already the rule for `OrderCreated`/
`OrderStatusChanged`; `PaymentStatusUpdateService` (new) follows the exact
same shape (row lock → transaction → dispatch after).

`OrderStatusTransitionService::transition()` dispatches **both** the
generic `OrderStatusChanged` (unchanged — `App\Listeners\
LogAdminOrderStatusChange` still uses it for the admin audit trail) and the
one specific event matching the new status, via an exhaustive `match`. A
listener that only cares about one status subscribes to the specific event
instead of filtering the generic one itself.

`PaymentStatusUpdateService` is **not wired to any HTTP endpoint or
Filament action yet** — no payment gateway webhook exists in this codebase
today. It's the seam a future one (e.g. a payment provider's webhook
controller) calls into; see `App\Services\PaymentStatusUpdateService`.

## Two kinds of Listener, two different delivery guarantees

- **`App\Listeners\NotifyAdminsOfNewOrder`** — "إرسال database notification
  للإدارة عند طلب جديد". An internal, same-database write via Filament's
  own `Notification::sendToDatabase()` (the admin panel already has
  `->databaseNotifications()` enabled — see `docs/ADMIN_PANEL.md`), sent to
  every active admin/staff user (`UserRole::adminCases()`). Not an external
  service call, so it isn't required to be queued by this task's own rule
  ("queue جميع الإشعارات الخارجية" — only *external* notifications must
  be); Filament's `DatabaseNotification` class happens to implement
  `ShouldQueue` internally regardless, which is harmless.
- **Nine `Send*PushNotification` listeners** (one per order event, plus
  `SendPaymentStatusChangedPushNotification`) — each builds an
  already-translated title/body (via `lang/{locale}/notifications.php`)
  and a small, non-sensitive data payload, then dispatches
  `App\Jobs\SendCustomerPushNotificationJob`. The listener itself is a
  plain class, not `ShouldQueue` — dispatching a queued job is already a
  cheap, synchronous operation, so queuing the listener *too* would just
  be a pointless extra hop. All the actual external I/O — and everything
  this task's requirements care about (queuing, retry, idempotency,
  invalid-token handling) — lives in the Job.

## The push abstraction: `App\Contracts\PushNotifier`

```php
interface PushNotifier
{
    public function sendToToken(DeviceToken $token, string $title, string $body, array $data = []): PushDeliveryResult;
}
```

Per-token, not per-user, deliberately: a user can have several device
tokens (multiple phones, reinstalls), and the caller needs to know each
token's own delivery outcome — specifically whether the provider says a
token is no longer valid — without affecting the user's other tokens.
`PushDeliveryResult` is a two-case enum: `Sent` or `InvalidToken`. A
genuinely transient failure (provider unreachable, rate limited) is a
**thrown exception**, not a return value — that's what lets the Job's own
retry/backoff take over.

Nothing in this codebase — no Event, Listener, or Job — depends on a
concrete push provider directly, only on this interface ("لا تربط منطق
الطلب مباشرة بمزود Firebase", "إنشاء ... service interface قابلة لتبديل
المزود"). Two implementations exist today, bound in
`App\Providers\AppServiceProvider::register()`:

- **`App\Notifications\Push\LogPushNotifier`** — the non-testing default.
  No real push provider (FCM/APNs/NativePHP push) is wired up yet, so this
  just logs what would have been sent and reports every token as
  delivered. Swapping in a real provider later is a one-line binding
  change in `AppServiceProvider` — nothing else in the app changes.
- **`App\Notifications\Push\FakePushNotifier`** — bound instead whenever
  `app()->environment('testing')` ("في بيئة الاختبار استخدم fake
  provider"): no test ever reaches a real provider, or even the log.
  Records every call in `->sent` (a plain array) for assertions, and lets
  a test pre-configure specific device-token IDs to simulate an invalid
  token (`->invalidTokenIds`) or a thrown transient failure
  (`->failingTokenIds`).

## The Job: `App\Jobs\SendCustomerPushNotificationJob`

The **only** place any code calls out to a push provider. Constructor:
`(int $userId, string $title, string $body, array $data, string $idempotencyKey)`
— the listener does all the translation/message-building up front, so the
Job only has to worry about *delivery*.

**Queuing** ("queue جميع الإشعارات الخارجية"): `implements ShouldQueue`.
Every customer push in the app goes through this one Job class.

**Retry/backoff** ("retry وbackoff واضحان"): `$tries = 5`, and
`backoff()` returns `[10, 30, 60, 300, 900]` (seconds) — five attempts
spread across 10 seconds to 15 minutes, long enough for a brief provider
outage to clear, short enough that a real delivery isn't stuck for hours.

**Idempotency** ("منع إرسال إشعار مكرر لنفس event عند retry"):
`App\Models\NotificationDispatchLog::claim($key)` atomically reserves the
key (via a unique-index `insertOrIgnore`, race-safe across two workers)
*before* anything is sent; if the claim fails, the job is a silent no-op —
this event was already handled. If claimed but sending then throws, the
claim is **released** before rethrowing, so a queue-level retry can claim
it again and actually try again. A key that stays claimed forever means
"delivery was attempted and didn't throw" — permanent, on purpose. The one
accepted gap: if a user has several device tokens and a *later* token
throws, *earlier* tokens in the same attempt may receive the push again on
retry (the whole per-event claim, not a per-token one) — acceptable given
most customers have a single active device token; per-token idempotency
would add real complexity for a vanishingly rare case.

**Invalid-token deactivation** ("إلغاء token غير الصالح عند رد المزود
بذلك"): for each of the user's active tokens, the Job calls
`sendToToken()` and matches on the result — `Sent` bumps
`DeviceToken.last_used_at`, `InvalidToken` sets
`DeviceToken.is_active = false`. An inactive token is never sent to again
by a later notification, but its row (and history) is kept, not deleted.

**No sensitive data in the payload** ("عدم تضمين بيانات حساسة داخل push
payload"): by convention, every listener's `data` array only ever contains
IDs and status values already visible to the customer through the app's
own API (`order_id`, `order_number`, `status`/`payment_status`) — never a
payment amount, transaction reference, gateway metadata, or anything from
`RestaurantSetting`'s encrypted fields. `SendPaymentStatusChangedPushNotificationTest`
asserts this explicitly.

## Device tokens

`App\Models\DeviceToken` — `user_id`, `token` (globally unique), `platform`
(`DevicePlatform`: `android`/`ios`), `device_name` (nullable, e.g. "Laith's
Pixel" — new in this task), `is_active`, `last_used_at`
("تخزين device tokens مع platform وdevice name وlast_used_at").

`App\Http\Controllers\Api\V1\DeviceTokenController` (new):

- `POST /api/v1/device-tokens` — registers/re-registers a token for the
  authenticated user (`token`, `platform`, optional `device_name`).
  `updateOrCreate` keyed on the unique `token` column: a token already
  registered (reinstalled app, or previously a different account's device)
  is reassigned to whoever is currently authenticated — the
  provider-issued token identifies the *device*, not who registered it
  first.
- `DELETE /api/v1/device-tokens` — deactivates a token owned by the
  authenticated user (`token` in the body); scoped through
  `$user->deviceTokens()`, so one user can never deactivate another's
  token.

## Bilingual text

`lang/en/notifications.php` / `lang/ar/notifications.php` (new) hold every
push/admin-notification title and body, one key per event
(`order_accepted`, `payment_paid`, `admin_new_order`, ...) — same
`__()`/`trans()` mechanism, and the same "one app-configured locale, not
per-user" convention, already used everywhere else in this codebase (cart
errors, order errors, validation messages). No per-user locale preference
exists in this app yet; adding one is a separate, larger piece of future
work, not something this task's scope covers.

## Testing

- `tests/Feature/Jobs/SendCustomerPushNotificationJobTest.php` — fan-out
  to every active token, `last_used_at` bump, invalid-token deactivation,
  idempotency (duplicate key sends nothing, different keys both send,
  a failed attempt releases its claim so a retry can succeed).
- `tests/Feature/Listeners/NotifyAdminsOfNewOrderTest.php` — every active
  admin notified, customers/inactive admins never are.
- `tests/Feature/Listeners/OrderPushNotificationListenersTest.php` — one
  case per order event, plus a check that different event types for the
  same order use distinct idempotency keys.
- `tests/Feature/Listeners/SendPaymentStatusChangedPushNotificationTest.php`
  — one case per real payment status, `pending` is skipped, and the
  payload/body never contains the amount or transaction reference.
- `tests/Feature/Services/PaymentStatusUpdateServiceTest.php` — status
  mirrored onto the order, `paid_at` stamped only for `paid`, event
  dispatched with the right `from`/`to`.
- `tests/Feature/Api/V1/DeviceTokenTest.php` — register, re-registration
  reassigns ownership, validation, auth required, deactivate is
  ownership-scoped.

All of the above run against `App\Notifications\Push\FakePushNotifier` —
no test ever depends on network access or a real push provider.

## Running notifications: the queue worker

See the root `README.md` "Running the queue worker" — nothing queued here
is actually delivered until a worker (`php artisan queue:work`) is
running.
